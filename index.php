<?php
    // Inclusion des fichiers d'en-tête, formulaire et pied de page
    include 'inc/header.php';
    include 'inc/formulaire.php';
    include 'inc/footer.php';
    include 'inc/navbar.php';
?>

<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Suivi Musculation — AscendForm</title>
        <!-- Icônes du site : .ico préféré + fallback PNG -->
        <link rel="icon" type="image/x-icon" href="media/logo_AscendForm.ico">
        <link rel="icon" type="image/png" href="media/logo_AscendForm.png">
        <link rel="shortcut icon" href="media/logo_AscendForm.ico">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="css/style.css" />
    </head>
    <body>
        <!-- Barre de navigation principale -->
        <?php navbar(); ?>

        <!-- En-tête  -->
        <?php header_index(); ?>

        <main class="container my-5">
            <div class="row g-4">

                <!-- Colonne de gauche : formulaire de saisie des séances -->
                <?php formulaire_Saisie_Seance(); ?>

                <!-- Colonne de droite : résumé et historique des séances -->
                <?php formulaire_Historique_Seance(); ?>

            </div>
        </main>

        <!-- footer -->
        <?php footer(); ?>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        <script src="js/app.js"></script>
    </body>
</html>
