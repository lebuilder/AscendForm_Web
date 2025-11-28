<?php
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
    </head>
    <body>
        <!-- Barre de navigation principale -->
        <?php navbar(); ?>

        <h1>Contactez-nous</h1>
        <form method="post">
            <label>Votre email</label>
            <input type="email" name="email" required>
            <label>Message</label>
            <textarea name="message" required></textarea>
            <input type="submit">
        </form>
        <?php
        if (isset($_POST['message']) and filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            $entete  = 'MIME-Version: 1.0' . "\r\n";
            $entete .= 'Content-type: text/html; charset=utf-8' . "\r\n";
            $entete .= 'From: webmaster@monsite.fr' . "\r\n";
            $entete .= 'Reply-to: ' . $_POST['email'];

            $message = '<h1>Message envoyé depuis la page Contact de monsite.fr</h1>
            <p><b>Email : </b>' . $_POST['email'] . '<br>
            <b>Message : </b>' . htmlspecialchars($_POST['message']) . '</p>';

            $retour = mail('destinataire@free.fr', 'Envoi depuis page Contact', $message, $entete);
            if($retour)
                echo '<p>Votre message a bien été envoyé.</p>';
        }
        ?>


        <!-- footer -->
        <?php footer(); ?>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    </body>
</html>