<?php
    require_once __DIR__.'/inc/auth.php';
    require_login();
    // Inclusion des fichiers d'en-tête, formulaire, pied de page et barre de navigation
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
        <title>Contact — AscendForm</title>
        <!-- Icônes du site : .ico préféré + fallback PNG -->
        <link rel="icon" type="image/x-icon" href="media/logo_AscendForm.ico">
        <link rel="icon" type="image/png" href="media/logo_AscendForm.png">
        <link rel="shortcut icon" href="media/logo_AscendForm.ico">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="css/contact.css" />
        <link rel="stylesheet" href="css/navbar.css" />
        <link rel="stylesheet" href="css/fond.css" />
    </head>
    <body>
        <!-- Barre de navigation principale -->
        <?php navbar(); ?>

        <!-- Formulaire de contact -->
        <?php formulaire_contact(); ?>
        
        <!-- footer -->
        <?php footer(); ?>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    </body>
</html>