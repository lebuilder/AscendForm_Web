<?php
    function navbar(){
        // Base path fixed to project root in XAMPP
        $base = '/dashboard/AscendForm';
    ?>
        <nav class="navbar navbar-expand-lg navbar-dark bg-transparent py-3">
            <div class="container">
                <a class="navbar-brand fw-bold d-flex align-items-center" href="<?= $base ?>/index.php">
                    <img src="<?= $base ?>/media/logo_AscendForm.png" alt="AscendForm" width="56" height="56" class="navbar-logo me-2"/>
                    <span>Suivi Musculation</span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
                    <span class="navbar-toggler-icon"></span>
                </button>
            <div class="collapse navbar-collapse" id="navMain">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link nav-link-animated" href="<?= $base ?>/index.php">
                            <span class="nav-icon">🏠</span>
                            <span class="nav-text">Accueil</span>
                        </a>
                    </li>
                                        <li class="nav-item">
                                            <a class="nav-link nav-link-animated" href="<?= $base ?>/stats.php">
                                                <span class="nav-icon">📊</span>
                                                <span class="nav-text">Statistiques</span>
                                            </a>
                                        </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-animated" href="<?= $base ?>/contact.php">
                            <span class="nav-icon">✉️</span>
                            <span class="nav-text">Contact</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-animated" href="<?= $base ?>/profil.php">
                            <span class="nav-icon">👤</span>
                            <span class="nav-text">Mon profil</span>
                        </a>
                    </li>
                </ul>
                <?php
                    // Derive first name from session: prefer first_name, fallback to user_name's first token
                    $firstName = '';
                    if (!empty($_SESSION['first_name'])) {
                        $firstName = (string)$_SESSION['first_name'];
                    } elseif (!empty($_SESSION['user_name'])) {
                        $parts = preg_split('/\s+/', (string)$_SESSION['user_name']);
                        $firstName = $parts[0] ?? '';
                    }

                    // Try to use avatar_path if available; otherwise fallback to initial badge
                    $avatarPath = $_SESSION['avatar_path'] ?? '';
                    $avatarUrl = '';
                    if (!empty($avatarPath)) {
                        if (preg_match('#^https?://#i', $avatarPath)) {
                            $avatarUrl = $avatarPath;
                        } elseif (substr($avatarPath, 0, 1) === '/') {
                            $avatarUrl = $avatarPath; // already absolute to domain
                        } else {
                            $avatarUrl = $base . '/' . ltrim($avatarPath, '/');
                        }
                    }
                ?>
                <?php if (!empty($firstName)): ?>
                <div class="d-flex align-items-center ms-3">
                    <span class="text-light me-3" style="font-weight: 500;">
                        Bonne séance, <?= htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') ?> 💪
                    </span>
                    <a href="<?= $base ?>/profil.php" class="text-decoration-none d-inline-flex align-items-center">
                        <span id="navbarAvatarSlot">
                        <?php if (!empty($avatarUrl)): ?>
                            <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Avatar" width="40" height="40" style="border-radius:50%; object-fit:cover; box-shadow: 0 2px 8px rgba(111, 211, 255, 0.4);">
                        <?php else: ?>
                            <div style="width: 42px; height: 42px; background: linear-gradient(135deg, #6fd3ff 0%, #4a9fd8 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; color: #0a1930; font-size: 1.1rem; box-shadow: 0 2px 8px rgba(111, 211, 255, 0.4); transition: all 0.3s ease;">
                                <?= strtoupper(substr($firstName, 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        </span>
                    </a>
                </div>
                <?php endif; ?>
            </div>
            </div>
        </nav>
    <?php
    }
?>