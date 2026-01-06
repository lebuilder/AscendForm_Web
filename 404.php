<?php
// Custom 404 page
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>404 • Page introuvable | AscendForm</title>
	<?php $base = '/dashboard/AscendForm'; ?>
	<link rel="icon" type="image/png" href="<?= $base ?>/media/logo_AscendForm.png">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="<?= $base ?>/css/fond.css">
	<link rel="stylesheet" href="<?= $base ?>/css/navbar.css">
	<link rel="stylesheet" href="<?= $base ?>/css/404.css">
</head>
<body>
	<?php require_once __DIR__.'/inc/navbar.php'; navbar(); ?>

	<main class="error-wrapper d-flex align-items-center">
		<div class="container">
			<div class="error-card mx-auto text-center">
				<img src="<?= $base ?>/media/logo_AscendForm.png" alt="AscendForm" class="mb-3 error-logo" width="72" height="72">
				<div class="error-code">404</div>
				<h1 class="error-title">Oups, page introuvable</h1>
				<p class="error-text">Le lien peut être incorrect ou la page a été déplacée.</p>

				<div class="d-flex flex-wrap gap-2 justify-content-center mt-3">
					<a href="<?= $base ?>/index.php" class="btn btn-accent align-items-center text-white">Retour à l'accueil</a>
				</div>

				<div class="mt-4 small ">Code d'erreur: 404 • AscendForm</div>
			</div>
		</div>
	</main>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

