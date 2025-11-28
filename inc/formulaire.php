<?php

function formulaire_Saisie_Seance(){
?>
    <!-- Formulaire de saisie des séances (gère plusieurs exercices et séries) -->
                <section id="tracker" class="col-12 col-lg-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Ajouter une séance</h5>
                            <form id="workoutForm">
                                <div class="mb-2" id="dateContainer">
                                    <label class="form-label">Date</label>
                                    <input type="date" id="date" class="form-control" />
                                </div>

                                <!-- Conteneur dynamique : chaque exercice ajouté crée une 'card' avec ses séries -->
                                <div id="exercisesContainer"></div>
                                <div id="exerciseButtonsContainer" class="mb-3">
                                    <div class="d-flex gap-2">
                                        <button id="addExerciseBtn" type="button" class="btn btn-sm btn-outline-light">Ajouter un exercice</button>
                                        <button id="clearExercisesBtn" type="button" class="btn btn-sm btn-outline-light">Réinitialiser</button>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Notes</label>
                                    <input type="text" id="notes" class="form-control" placeholder="Remarques courtes" />
                                </div>
                                <div class="d-grid">
                                    <button class="btn btn-primary" type="submit">Ajouter la séance</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </section>

<?php
}

function formulaire_Historique_Seance(){
    ?>
        <section id="progress" class="col-12 col-lg-6">
            <!-- Résumé rapide (nombre de séances) -->
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h5 class="card-title">Résumé rapide</h5>
                    <p id="summary" class="card-text">Aucune séance pour l'instant.</p>
                </div>
            </div>

            <!-- Historique des séances -->
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="card-title mb-0">Historique</h5>
                        <div>
                            <button id="exportJsonBtn" type="button" class="btn btn-sm btn-outline-light">Exporter JSON</button>
                        </div>
                    </div>
                            <ul id="workoutList" class="list-group list-group-flush"></ul>
                </div>
            </div>
        </section>
    <?php
}
?>


