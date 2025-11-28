<?php
    // Inclusion des fichiers d'en-tête, formulaire, pied de page et barre de navigation
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
        <title>Login - AscendForm</title>
        <!-- Icônes du site : .ico préféré + fallback PNG -->
        <link rel="icon" type="image/x-icon" href="media/logo_AscendForm.ico">
        <link rel="icon" type="image/png" href="media/logo_AscendForm.png">
        <link rel="shortcut icon" href="media/logo_AscendForm.ico">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="css/login.css" />
    </head>
    <body>
        <!--Formulaire de connexion -->
        <section id="login" class="col-12 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Connexion</h5>
                    <form id="LoginForm">
                        <div class="mb-2" id="loginContainer">
                            <label class="form-label">login</label>
                            <input type="text" id="login" class="form-control" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mot de passe</label>
                            <input type="password" id="password" class="form-control" />
                        </div>

                        <div class="d-grid">
                            <button class="btn btn-primary" type="submit">Se connecter</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <!--Footer-->
        <?php footer(); ?>
    </body>
</html>