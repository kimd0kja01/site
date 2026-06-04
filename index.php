<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclure les fonctions
include 'functions.php';

$error = '';

// Si déjà connecté, rediriger vers dashboard
if (isset($_SESSION['idUser'])) {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pseudo = $_POST['pseudo'] ?? '';
    $mdp = $_POST['mdp'] ?? '';
    
    if (!empty($pseudo) && !empty($mdp)) {
        try {
            $pdo = pdo_connect_mysql();
            
            // Vérifier si l'utilisateur existe dans la table Securite
            $stmt = $pdo->prepare('SELECT idSecurite, pseudo, mdp FROM Sécurité WHERE pseudo = ?');
            $stmt->execute([$pseudo]);
            $user = $stmt->fetch();
            
            if ($user) {
                if ($mdp === $user['mdp']) {
                    $_SESSION['idUser'] = $user['idSecurite'];
                    $_SESSION['pseudo'] = $user['pseudo'];
                    header('Location: dashboard.php');
                    exit();
                } else {
                    $error = 'Identifiant ou mot de passe incorrect';
                }
            } else {
                $error = 'Identifiant ou mot de passe incorrect';
            }
        } catch (PDOException $e) {
            $error = 'Erreur BD: ' . $e->getMessage();
        }
    } else {
        $error = 'Veuillez remplir tous les champs';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>M.A.N.A - Connexion</title>
    <link rel="stylesheet" href="login.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🌾</text></svg>">
</head>
<body>
    <img src="/views/image2.png" alt="M.A.N.A Logo" class="logo">
    
    <div class="container">
        
        
        <div class="form-card">
            <h1>Connexion</h1>
            <?php if ($error): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="pseudo">Pseudo</label>
                    <div class="input-wrapper">
                        <span class="input-icon">👤</span>
                        <input type="text" id="pseudo" name="pseudo" placeholder="Entrez votre pseudo" value="<?= htmlspecialchars($_POST['pseudo'] ?? '') ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="mdp">Mot de passe</label>
                    <div class="input-wrapper">
                        <span class="input-icon">🔒</span>
                        <input type="password" id="mdp" name="mdp" placeholder="Entrez votre mot de passe" required>
                    </div>
                </div>
                
                <button type="submit" class="btn-signin">Se connecter</button>
            </form>

            <div class="create-account-section">
                <p>Pas de compte ? <a href="register.php" class="btn-create-account-link">Créer un compte</a></p>
            </div>
        </div>

        <div class="footer-text">
            <p>M.A.N.A © 2026 - Gestion agricole moderne</p>
        </div>
    </div>

    <script>
        // Aucun script nécessaire pour cette version simplifiée
    </script>
</body>
</html>