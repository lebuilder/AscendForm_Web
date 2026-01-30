// AscendForm - Client-side log filtering, persistence, and raw view
(function(){
  const listEl = document.getElementById('logsList');
  if(!listEl) return;
  let logsRaw = [];
  try { logsRaw = JSON.parse(listEl.getAttribute('data-logs')||'[]'); } catch(e) {}
  const filterLevelEl = document.getElementById('filterLevel');
  const filterActionEl = document.getElementById('filterAction');
  const filterEmailEl = document.getElementById('filterEmail');
  const applyBtn = document.getElementById('applyFilters');
  const filterLevelChange = function(){
    const lvl = filterLevelEl.value.trim();
    if(lvl === 'ADMIN'){
      // Recharger côté serveur pour charger admin_activity.log
      const params = new URLSearchParams(window.location.search);
      params.set('level','ADMIN');
      window.location.search = params.toString();
    } else {
      // Si on vient de ADMIN, enlever le param pour revenir aux logs normaux
      const params = new URLSearchParams(window.location.search);
      if(params.get('level')==='ADMIN'){
        params.delete('level');
        window.location.search = params.toString();
      }
    }
  };
  const resetBtn = document.getElementById('resetFilters');
  const toggleRawBtn = document.getElementById('toggleRaw');
  const countBadge = document.getElementById('countBadge');

  // Persistence keys
  const LS_KEYS = {
    level: 'logsFilterLevel',
    action: 'logsFilterAction',
    email: 'logsFilterEmail',
    raw: 'logsRawView'
  };

  // Load persisted
  filterLevelEl.value = localStorage.getItem(LS_KEYS.level) || '';
  filterActionEl.value = localStorage.getItem(LS_KEYS.action) || '';
  filterEmailEl.value = localStorage.getItem(LS_KEYS.email) || '';
  const rawView = localStorage.getItem(LS_KEYS.raw) === '1';
  if(rawView) toggleRawBtn.classList.add('active');

  function summarize(log){
    const level = log.level || 'INFO';
    const parts = [];
    if(log.data){
      if('success' in log.data){ parts.push(log.data.success ? '✅ succès' : '❌ échec'); }
      if(log.status){ parts.push('statut:'+log.status); }
      if(log.duration_ms){ parts.push(log.duration_ms+'ms'); }
      if(log.changes){ parts.push('changes:'+log.changes.length); }
      if(log.data.error){ parts.push('erreur:'+log.data.error); }
    }
    return parts.join(' | ');
  }

  function render(logs){
    listEl.innerHTML = '';
    if(!logs.length){
      const d = document.createElement('div');
      d.className='alert alert-info';
      d.textContent='Aucun log après filtrage.';
      listEl.appendChild(d); return;
    }
    logs.forEach(l => {
      const lvl = l.level || 'INFO';
      const div = document.createElement('div');
      div.className = 'log-entry '+lvl;
      const isAdminTag = (l.level === 'ADMIN' || (l.data && l.data.admin === true));
      const levelBadge = `<span class="log-level-badge log-level-${lvl}">${lvl}${isAdminTag?' · ADMIN':''}</span>`;
      const replayEligible = ['email_send','email_validation','resend_validation_email'].includes(l.action);
      div.innerHTML = `
        <div class="log-line">
          ${levelBadge}
          <span class="log-timestamp">${escapeHtml(l.timestamp||'')}</span>
          <span class="log-action">${escapeHtml(l.action||'')}</span>
          ${l.email?`<span class="log-email">${escapeHtml(l.email)}</span>`:''}
          <span class="log-ip">[${escapeHtml(l.ip||'')}]</span>
          ${l.request_id?`<span class="log-req" style="color:#666;font-size:.6rem;">${escapeHtml(l.request_id)}</span>`:''}
        </div>
        <div class="log-summary">${escapeHtml(summarize(l))}</div>
      `;
      if(replayEligible){
        const btn = document.createElement('button');
        btn.type='button';
        btn.className='btn btn-outline-info btn-sm replay-btn';
        btn.textContent='↻ Rejouer';
        btn.onclick=()=>replayAction(l);
        div.appendChild(btn);
      }
      div.addEventListener('click', (e)=>{
        if(e.target.closest('.replay-btn')) return; // ignore click
        div.classList.toggle('expanded');
        if(div.querySelector('.log-json')){ div.querySelector('.log-json').remove(); return; }
        if(!rawView) return; // only show raw when raw view active
        const pre = document.createElement('div');
        pre.className='log-json';
        pre.textContent = JSON.stringify(l,null,2);
        div.appendChild(pre);
      });
      listEl.appendChild(div);
    });
    updateCountBadge(logs.length);
  }

  function escapeHtml(str){ return String(str).replace(/[&<>"]/g, s=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;"}[s])); }

  function applyFilters(){
    const lvl = filterLevelEl.value.trim();
    const act = filterActionEl.value.trim();
    const email = filterEmailEl.value.trim().toLowerCase();
    localStorage.setItem(LS_KEYS.level, lvl);
    localStorage.setItem(LS_KEYS.action, act);
    localStorage.setItem(LS_KEYS.email, email);
    const filtered = logsRaw.filter(l => {
      const lLevel = l.level || 'INFO';
      if(lvl && lLevel !== lvl) return false;
      if(act && (l.action||'') !== act) return false;
      if(email && !(l.email||'').toLowerCase().includes(email)) return false;
      return true;
    });
    render(filtered);
  }

  function resetFilters(){
    filterLevelEl.value=''; filterActionEl.value=''; filterEmailEl.value='';
    Object.values(LS_KEYS).forEach(k=>{ if(k!=='raw') localStorage.removeItem(k); });
    render(logsRaw);
  }

  function updateCountBadge(shown){
    countBadge.innerHTML = `<strong>${shown}</strong> / ${logsRaw.length} événements`;
  }

  function toggleRaw(){
    const active = toggleRawBtn.classList.toggle('active');
    localStorage.setItem(LS_KEYS.raw, active ? '1' : '0');
    // Re-render to attach/detach JSON blocks if expanded
    renderCurrent();
  }

  function renderCurrent(){
    // Re-apply filters to maintain current view
    applyFilters();
  }

  function replayAction(log){
    if(!confirm('Relancer cette action ?')) return;
    fetch('../../services/email/resend_validation.php', {
      method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:'email='+encodeURIComponent(log.email||'')+'&action='+encodeURIComponent(log.action||'')
    }).then(r=>r.json()).then(j=>{
      alert(j.success ? 'Action rejouée.' : 'Échec: '+j.error);
    }).catch(()=>alert('Erreur réseau'));
  }

  applyBtn.addEventListener('click', applyFilters);
  filterLevelEl.addEventListener('change', filterLevelChange);
  resetBtn.addEventListener('click', resetFilters);
  toggleRawBtn.addEventListener('click', toggleRaw);

  // Initial render
  applyFilters();
})();
