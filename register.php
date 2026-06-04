<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['Prénom'] ?? '');
    $mail = trim($_POST['mail'] ?? '');
    $pseudo = trim($_POST['pseudo'] ?? '');
    $mdp = $_POST['mdp'] ?? '';
    $confirm_mdp = $_POST['confirm_mdp'] ?? '';

    if (!empty($nom) && !empty($prenom) && !empty($mail) && !empty($pseudo) && !empty($mdp) && !empty($confirm_mdp)) {
        if ($mdp === $confirm_mdp) {
            if (strlen($pseudo) >= 3 && strlen($mdp) >= 6 && filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                try {
                    $pdo = pdo_connect_mysql();

                    $stmt = $pdo->prepare('SELECT idSecurite FROM Sécurité WHERE pseudo = ?');
                    $stmt->execute([$pseudo]);
                    $existingPseudo = $stmt->fetch();

                    $stmt = $pdo->prepare('SELECT idUser FROM User WHERE Mail = ?');
                    $stmt->execute([$mail]);
                    $existingMail = $stmt->fetch();

                    if (!$existingPseudo && !$existingMail) {
                        $mdp_hash = password_hash($mdp, PASSWORD_DEFAULT);

                        $stmt = $pdo->prepare('INSERT INTO User (Nom, prenom, Mail) VALUES (?, ?, ?)');
                        $stmt->execute([$nom, $prenom, $mail]);

                        $idUser = $pdo->lastInsertId();

                        $stmt = $pdo->prepare('INSERT INTO Sécurité (pseudo, mdp, idUser) VALUES (?, ?, ?)');
                        $stmt->execute([$pseudo, $mdp_hash, $idUser]);

                        $success = 'Compte créé avec succès ! Vous pouvez maintenant vous connecter.';
                    } else {
                        $error = 'Ce pseudo ou cette adresse mail est déjà utilisée.';
                    }
                } catch (PDOException $e) {
                    $error = 'Erreur BD : ' . $e->getMessage();
                }
            } else {
                $error = 'Pseudo minimum 3 caractères, mot de passe minimum 6 caractères, et mail valide requis.';
            }
        } else {
            $error = 'Les mots de passe ne correspondent pas.';
        }
    } else {
        $error = 'Veuillez remplir tous les champs.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>M.A.N.A - Inscription</title>
    <link rel="stylesheet" href="login.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🌾</text></svg>">
</head>
<body>
    <img src="/views/image2.png" alt="M.A.N.A Logo" class="logo">
    
    <div class="container">
        
        <div class="form-card">
            <h1>Inscription</h1>
            <?php if ($error): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="success-message"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="nom">Nom</label>
                    <div class="input-wrapper">
                        <span class="input-icon">📝</span>
                        <input type="text" id="nom" name="nom" placeholder="Entrez votre nom" value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="prenom">Prénom</label>
                    <div class="input-wrapper">
                        <span class="input-icon">📝</span>
                        <input type="text" id="prenom" name="prenom" placeholder="Entrez votre prénom" value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="mail">Adresse mail</label>
                    <div class="input-wrapper">
                        <span class="input-icon">📧</span>
                        <input type="email" id="mail" name="mail" placeholder="Entrez votre adresse mail" value="<?= htmlspecialchars($_POST['mail'] ?? '') ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="pseudo">Pseudo</label>
                    <div class="input-wrapper">
                        <span class="input-icon">👤</span>
                        <input type="text" id="pseudo" name="pseudo" placeholder="Choisissez votre pseudo" value="<?= htmlspecialchars($_POST['pseudo'] ?? '') ?>" required minlength="3">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="mdp">Mot de passe</label>
                    <div class="input-wrapper">
                        <span class="input-icon">🔒</span>
                        <input type="password" id="mdp" name="mdp" placeholder="Choisissez un mot de passe" required minlength="6">
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_mdp">Confirmer le mot de passe</label>
                    <div class="input-wrapper">
                        <span class="input-icon">🔒</span>
                        <input type="password" id="confirm_mdp" name="confirm_mdp" placeholder="Confirmez votre mot de passe" required minlength="6">
                    </div>
                </div>
                
                <button type="submit" class="btn-signup">Créer un compte</button>
            </form>

            <p class="login-hint">Déjà inscrit ? <a href="index.php">Se connecter</a></p>
        </div>

        <div class="footer-text">
            <p>M.A.N.A © 2026 - Gestion agricole moderne</p>
        </div>
    </div>
</body>
</html>