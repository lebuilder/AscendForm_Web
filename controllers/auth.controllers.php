<?php
declare(strict_types=1);

// Auth controller helpers for password hashing and basic client ops
// Uses PHP's password_hash/password_verify with defaults (bcrypt/argon2i depending on build)

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

/**
 * Hash a plain-text password using PHP's native password_hash.
 */
function auth_hash_password(string $password): string {
	// Use default algorithm and sensible cost; allow override via env if needed
	$algo = PASSWORD_DEFAULT;
	$options = [];
	$costEnv = getenv('ASCENDFORM_BCRYPT_COST');
	if ($costEnv !== false && is_numeric($costEnv)) {
		$options['cost'] = (int)$costEnv;
	}
	return password_hash($password, $algo, $options);
}

/**
 * Verify a password against its stored hash; rehash if algorithm/cost changed.
 * Returns an array: [bool $valid, ?string $newHash]
 * - If $valid is true and $newHash is not null, persist $newHash to DB.
 */
function auth_verify_password(string $password, string $hash): array {
	$valid = password_verify($password, $hash);
	$newHash = null;
	if ($valid && password_needs_rehash($hash, PASSWORD_DEFAULT)) {
		$newHash = auth_hash_password($password);
	}
	return [$valid, $newHash];
}

/**
 * Find a client by email. Returns associative array or null.
 */
function auth_find_client_by_email(PDO $pdo, string $email): ?array {
	$sql = 'SELECT * FROM clients WHERE email = :email LIMIT 1';
	$stmt = $pdo->prepare($sql);
	$stmt->execute([':email' => $email]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	return $row ?: null;
}

/**
 * Create a new client with hashed password. Returns inserted ID.
 * Expected $data keys: email, password, first_name, last_name
 */
function auth_create_client(PDO $pdo, array $data): int {
	if (empty($data['email']) || empty($data['password']) || empty($data['first_name']) || empty($data['last_name'])) {
		throw new InvalidArgumentException('Missing required fields.');
	}

	$hash = auth_hash_password($data['password']);
	$sql = 'INSERT INTO clients (email, password_hash, first_name, last_name, phone, avatar_path, birthdate, height_cm, weight_kg)
			VALUES (:email, :password_hash, :first_name, :last_name, :phone, :avatar_path, :birthdate, :height_cm, :weight_kg)';
	$stmt = $pdo->prepare($sql);
	$stmt->execute([
		':email' => $data['email'],
		':password_hash' => $hash,
		':first_name' => $data['first_name'],
		':last_name' => $data['last_name'],
		':phone' => $data['phone'] ?? null,
		':avatar_path' => $data['avatar_path'] ?? null,
		':birthdate' => $data['birthdate'] ?? null,
		':height_cm' => $data['height_cm'] ?? null,
		':weight_kg' => $data['weight_kg'] ?? null,
	]);
	return (int)$pdo->lastInsertId();
}

/**
 * Attempt login: returns client array on success, or null on failure.
 * If hash needs rehash, the updated hash is persisted.
 */
function auth_login(PDO $pdo, string $email, string $password): ?array {
	$client = auth_find_client_by_email($pdo, $email);
	if (!$client) return null;
	[$ok, $newHash] = auth_verify_password($password, $client['password_hash']);
	if (!$ok) return null;

	if ($newHash !== null) {
		$up = $pdo->prepare('UPDATE clients SET password_hash = :h, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
		$up->execute([':h' => $newHash, ':id' => $client['id']]);
		$client['password_hash'] = $newHash;
	}

	// Update last_login_at
	$pdo->prepare('UPDATE clients SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id')->execute([':id' => $client['id']]);

	// Set minimal session values (adapt as needed)
	$_SESSION['client_id'] = $client['id'];
	$_SESSION['user_name'] = ($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? '');
	// Also keep first/last names and avatar in session for UI (navbar, etc.)
	$_SESSION['first_name'] = $client['first_name'] ?? '';
	$_SESSION['last_name'] = $client['last_name'] ?? '';
	$_SESSION['avatar_path'] = $client['avatar_path'] ?? null;
	$_SESSION['user_email'] = $client['email'];
	$_SESSION['is_admin'] = !empty($client['is_admin']) ? 1 : 0;

	return $client;
}

/**
 * Simple logout
 */
function auth_logout(): void {
	$_SESSION = [];
	if (ini_get('session.use_cookies')) {
		$params = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
	}
	session_destroy();
}
