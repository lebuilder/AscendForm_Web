<?php
    function navbar(){
    ?>
        <nav class="navbar navbar-expand-lg navbar-dark bg-transparent py-3">
            <div class="container">
                <a class="navbar-brand fw-bold d-flex align-items-center" href="#">
                    <img src="media/logo_AscendForm.png" alt="AscendForm" width="56" height="56" class="navbar-logo me-2"/>
                    <span>Suivi Musculation</span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
                    <span class="navbar-toggler-icon"></span>
                </button>
            <div class="collapse navbar-collapse" id="navMain">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Accueil</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                    <li class="nav-item"><a class="nav-link" href="profil.php">Mon profil</a></li>
                </ul>
            </div>
            </div>
        </nav>
    <?php
    }
?>