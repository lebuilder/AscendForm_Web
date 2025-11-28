<?php
    // Inclusion des fichiers d'en-tête, formulaire et pied de page
    include 'inc/header.php';
    include 'inc/formulaire.php';
    include 'inc/footer.php';
    include 'inc/navbar.php';
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Mon Profile — AscendForm</title>
        <!-- Icônes du site : .ico préféré + fallback PNG -->
        <link rel="icon" type="image/x-icon" href="media/logo_AscendForm.ico">
        <link rel="icon" type="image/png" href="media/logo_AscendForm.png">
        <link rel="shortcut icon" href="media/logo_AscendForm.ico">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="css/style.css" />
    </head>
    <body>
        <!--nav bar-->
        <?php navbar(); ?>

        <!--Header
        <?php //header_profil(); ?>
        -->
        <!--Footer-->
        <?php footer(); ?>
    </body>
</html>