// JS minimal pour gérer l'ajout d'entraînements, la structure des séances
// et le stockage local (localStorage).
// Format stocké pour une séance:
// { date: 'YYYY-MM-DD', notes: '...', exercises: [ { name: 'Exo', sets: [ { reps: '8', weight: '80' }, ... ] }, ... ] }
(function(){
  // Références DOM principales
  const form = document.getElementById('workoutForm');
  const list = document.getElementById('workoutList');
  const summary = document.getElementById('summary');
  const exercisesContainer = document.getElementById('exercisesContainer');
  const addExerciseBtn = document.getElementById('addExerciseBtn');
  const clearExercisesBtn = document.getElementById('clearExercisesBtn');

  // Charge le tableau de séances depuis localStorage (ou [] si absent)
  function loadWorkouts(){
    try{ return JSON.parse(localStorage.getItem('workouts')||'[]'); }
    catch(e){ return []; }
  }

  // Sauvegarde le tableau de séances en JSON dans localStorage
  function saveWorkouts(arr){
    localStorage.setItem('workouts', JSON.stringify(arr));
  }

  // Protège les valeurs affichées contre l'injection HTML
  function escapeHtml(s){
    if(s === null || s === undefined) return '';
    return String(s).replace(/[&<>"'`]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;', '`':'&#96;'}[c];});
  }

  // Rend la liste d'historique dans le DOM à partir des données stockées
  function render(){
    const items = loadWorkouts();
    list.innerHTML = '';
    if(items.length===0){
      summary.textContent = "Aucune séance enregistrée.";
      return;
    }
    summary.textContent = `${items.length} séance(s) enregistrée(s)`;
    // on affiche les séances les plus récentes en premier
    items.slice().reverse().forEach((w, i)=>{
      const li = document.createElement('li');
      li.className = 'list-group-item';
      // construire le HTML des exercices et séries pour cette séance
      let exercisesHtml = '';
      (w.exercises||[]).forEach((ex, exIdx)=>{
        exercisesHtml += `<div class="mb-2"><div class="fw-bold">${escapeHtml(ex.name)}</div><ul class="mb-0 small">`;
        (ex.sets||[]).forEach((s, sIdx)=>{
          exercisesHtml += `<li>Série ${sIdx+1}: ${escapeHtml(s.reps)} ${s.weight?('· '+escapeHtml(s.weight)+' kg') : ''}</li>`;
        });
        exercisesHtml += `</ul></div>`;
      });

      li.innerHTML = `<div>
          <div class="fw-bold">${escapeHtml(w.date)}</div>
          <div class="small text-muted mb-2">${w.notes? escapeHtml(w.notes) : ''}</div>
          ${exercisesHtml}
        </div>
        <div class="mt-2"><button class="btn btn-sm btn-outline-light" data-index="${i}">Suppr</button></div>`;

      const btn = li.querySelector('button');
      btn.addEventListener('click', ()=>{ removeAt(items.length-1 - i); });
      list.appendChild(li);
    });
  }

  // Supprime une séance par index (après confirmation)
  function removeAt(idx){
    const items = loadWorkouts();
    if(idx<0||idx>=items.length) return;
    if(!confirm('Supprimer cette séance ?')) return;
    items.splice(idx,1);
    saveWorkouts(items);
    render();
  }

  // Crée une ligne de série (inputs pour répétitions et poids)
  function createSetRow(setData){
    const row = document.createElement('div');
    row.className = 'd-flex gap-2 align-items-center mb-2 set-row';

    const repsInput = document.createElement('input');
    repsInput.type = 'number';
    repsInput.inputMode = 'numeric';
    repsInput.pattern = '\\d*';
    repsInput.min = '0';
    repsInput.step = '1';
    repsInput.className = 'form-control form-control-sm set-reps';
    repsInput.placeholder = 'Rép (ex: 8)';
    repsInput.value = escapeHtml((setData&&setData.reps)||'');

    const weightInput = document.createElement('input');
    weightInput.type = 'number';
    weightInput.inputMode = 'decimal';
    weightInput.min = '0';
    weightInput.step = '0.1';
    weightInput.className = 'form-control form-control-sm set-weight';
    weightInput.placeholder = 'Poids (kg)';
    weightInput.value = escapeHtml((setData&&setData.weight)||'');

    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'btn btn-sm btn-outline-light btn-remove-set';
    removeBtn.textContent = '✖';
    removeBtn.addEventListener('click', ()=>{ row.remove(); });

    // Nettoyage des saisies :
    // - répétitions : conserver uniquement les chiffres (entiers)
    repsInput.addEventListener('input', ()=>{
      repsInput.value = repsInput.value.replace(/\D+/g, '');
    });
    // - poids : autoriser chiffres et un seul point décimal
    weightInput.addEventListener('input', ()=>{
      weightInput.value = weightInput.value.replace(/[^0-9.]+/g, '');
      const parts = weightInput.value.split('.');
      if(parts.length>2){
        weightInput.value = parts[0] + '.' + parts.slice(1).join('');
      }
    });

    row.appendChild(repsInput);
    row.appendChild(weightInput);
    row.appendChild(removeBtn);
    return row;
  }

  // Crée une carte DOM pour un exercice, contenant le nom et ses séries
  function createExerciseCard(exData){
    const card = document.createElement('div');
    card.className = 'card mb-3 exercise-card';
    const cardBody = document.createElement('div');
    cardBody.className = 'card-body';
    cardBody.innerHTML = `
      <div class="d-flex justify-content-between align-items-start mb-2">
        <label class="form-label mb-0">Exercice</label>
        <button type="button" class="btn btn-sm btn-outline-light btn-remove-exercise">Suppr exercice</button>
      </div>
    `;
    const nameInput = document.createElement('input');
    nameInput.type = 'text';
    nameInput.className = 'form-control mb-2 exercise-name';
    nameInput.placeholder = 'Nom de l\'exercice (ex: Développé couché)';
    nameInput.value = exData && exData.name ? exData.name : '';

    // Muscle selector
    const muscleSelect = document.createElement('select');
    muscleSelect.className = 'form-select form-select-sm mb-2 exercise-muscle';
    const muscles = ['','Pectoraux','Dos','Jambes','Épaules','Biceps','Triceps','Abdos'];
    muscles.forEach(m => {
      const opt = document.createElement('option');
      opt.value = m; opt.textContent = m ? m : '— Groupe musculaire —';
      // Force style on options (may be limited by OS rendering)
      opt.style.backgroundColor = 'var(--night-bg)';
      opt.style.color = 'var(--accent)';
      muscleSelect.appendChild(opt);
    });
    if (exData && exData.muscle) muscleSelect.value = exData.muscle;

    // Hard-force select appearance with inline styles
    muscleSelect.style.setProperty('background', 'var(--night-bg)', 'important');
    muscleSelect.style.setProperty('backgroundColor', 'var(--night-bg)', 'important');
    muscleSelect.style.setProperty('color', 'var(--accent)', 'important');
    muscleSelect.style.setProperty('borderColor', 'var(--accent)', 'important');
    muscleSelect.style.setProperty('backgroundImage', 'none', 'important');

    const setsContainer = document.createElement('div');
    setsContainer.className = 'sets-container mb-2';

    const addSetBtn = document.createElement('button');
    addSetBtn.type = 'button';
    addSetBtn.className = 'btn btn-sm btn-outline-light mb-2';
    addSetBtn.textContent = 'Ajouter une série';
    addSetBtn.addEventListener('click', ()=>{ setsContainer.appendChild(createSetRow()); });

    cardBody.appendChild(nameInput);
    cardBody.appendChild(muscleSelect);
    cardBody.appendChild(setsContainer);
    cardBody.appendChild(addSetBtn);
    card.appendChild(cardBody);

    // Gestionnaire suppression d'un exercice (avec confirmation)
    card.querySelector('.btn-remove-exercise').addEventListener('click', ()=>{ 
      // Empêcher la suppression s'il ne reste qu'un seul exercice
      const exerciseCount = exercisesContainer.querySelectorAll('.exercise-card').length;
      if(exerciseCount <= 1){
        showError('Il doit y avoir au moins un exercice dans le formulaire.', card.querySelector('.card-body'));
        return;
      }
      if(confirm('Supprimer cet exercice ?')) card.remove(); 
    });

    // initialize sets
    const initialSets = (exData && exData.sets && exData.sets.length) ? exData.sets : [{}];
    initialSets.forEach(s=> setsContainer.appendChild(createSetRow(s)));

    return card;
  }

  function addExercise(exData){
    const card = createExerciseCard(exData);
    exercisesContainer.appendChild(card);
    // expose muscle value in data when collecting
    // nothing extra needed; we'll read from DOM later
  }

  // Boutons pour ajouter ou réinitialiser les exercices dans le formulaire
  addExerciseBtn.addEventListener('click', ()=> addExercise());
  clearExercisesBtn.addEventListener('click', ()=>{ 
    // Vérifier si une confirmation existe déjà
    if(document.querySelector('.confirm-reset')) return;
    
    // Créer la boîte de confirmation
    const confirmBox = document.createElement('div');
    confirmBox.className = 'confirm-reset';
    confirmBox.innerHTML = `
      <div class="confirm-message">🔄 Réinitialiser tous les exercices ?</div>
      <div class="confirm-buttons">
        <button class="btn-confirm-yes">Oui</button>
        <button class="btn-confirm-no">Non</button>
      </div>
    `;
    
    const container = document.getElementById('exerciseButtonsContainer');
    container.appendChild(confirmBox);
    setTimeout(()=>{ confirmBox.classList.add('show'); }, 10);
    
    // Bouton Oui
    confirmBox.querySelector('.btn-confirm-yes').addEventListener('click', ()=>{
      exercisesContainer.innerHTML=''; 
      addExercise();
      confirmBox.classList.remove('show');
      setTimeout(()=>confirmBox.remove(), 300);
    });
    
    // Bouton Non
    confirmBox.querySelector('.btn-confirm-no').addEventListener('click', ()=>{
      confirmBox.classList.remove('show');
      setTimeout(()=>confirmBox.remove(), 300);
    });
  });

  // Fonction pour afficher un message d'erreur temporaire
  function showError(message, targetElement) {
    // Supprimer les anciens messages d'erreur
    const oldError = targetElement.querySelector('.error-message');
    if(oldError) oldError.remove();
    
    const errorMsg = document.createElement('div');
    errorMsg.className = 'error-message';
    errorMsg.textContent = '⚠️ ' + message;
    targetElement.appendChild(errorMsg);
    setTimeout(()=>{ errorMsg.classList.add('show'); }, 10);
    setTimeout(()=>{ 
      errorMsg.classList.remove('show');
      setTimeout(()=>errorMsg.remove(), 300);
    }, 3000);
  }

  // Soumission du formulaire : collecte des données structurées
  form.addEventListener('submit', function(e){
    e.preventDefault();
    const date = document.getElementById('date').value;
    const notes = document.getElementById('notes').value.trim();
    if(!date){ 
      showError('Veuillez renseigner la date.', document.getElementById('dateContainer'));
      return; 
    }

    const exerciseCards = Array.from(document.querySelectorAll('.exercise-card'));
    const exercises = [];
    exerciseCards.forEach(card=>{
      const name = card.querySelector('.exercise-name').value.trim();
      const muscle = (card.querySelector('.exercise-muscle')?.value || '').trim();
      if(!name) return; // skip empty
      const sets = [];
      const setRows = Array.from(card.querySelectorAll('.set-row'));
      setRows.forEach(r=>{
        const reps = r.querySelector('.set-reps').value.trim();
        const weight = r.querySelector('.set-weight').value.trim();
        if(!reps && !weight) return; // skip empty rows
        sets.push({ reps, weight });
      });
      if(sets.length>0) exercises.push({ name, muscle, sets });
    });

    if(exercises.length===0){ 
      showError('Ajoute au moins un exercice avec au moins une série.', document.getElementById('exerciseButtonsContainer'));
      return; 
    }

    // Save to localStorage (legacy)
    const items = loadWorkouts();
    items.push({ date, notes, exercises });
    saveWorkouts(items);

    // Save to database
    const payload = {
      date,
      notes,
      exercises: exercises.map(ex => ({
        name: ex.name,
        muscle: ex.muscle || '',
        // send raw sets so server can aggregate properly
        sets: ex.sets.map(s => ({
          reps: (s.reps || '').trim(),
          weight: (s.weight || '').trim()
        }))
      }))
    };

    fetch('services/seances/save_seance.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
      const msgTarget = document.getElementById('summary');
      if(data.success){
        console.log('✓ Séance sauvegardée en base:', data.inserted, 'exercices');
        if (msgTarget) { msgTarget.textContent = `${loadWorkouts().length} séance(s) enregistrée(s) • dernière sauvegarde OK`; }
      } else {
        console.warn('Erreur sauvegarde DB:', data.error);
        if (msgTarget) { msgTarget.textContent = '⚠️ Erreur de sauvegarde en base. Vos données locales sont enregistrées.'; }
      }
    })
    .catch(err => console.error('Erreur réseau:', err));

    // Réinitialiser le formulaire : vider la liste d'exercices et ajouter un exercice vide par défaut
    form.reset();
    exercisesContainer.innerHTML = '';
    addExercise();
    render();
  });

  // État initial : une carte exercice vide et affichage de l'historique
  addExercise();
  render();

})();
