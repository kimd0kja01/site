<?php
function template_header($title) {
    echo <<<EOT
    <!DOCTYPE html>
    <html>
        <head>
            <meta charset="UTF-8">
            <title>M.A.N.A</title>
            <script src="https://cdn.anychart.com/releases/v8/js/anychart-base.min.js"></script>
            <script src="https://cdn.anychart.com/releases/v8/js/anychart-data-adapter.min.js"></script>
            <script src="https://cdn.anychart.com/releases/v8/js/anychart-exports.min.js"></script>
            <script src="https://cdn.anychart.com/releases/v8/js/anychart-vml.min.js"></script>
            <script src="https://cdn.tailwindcss.com"></script>
            <link rel="stylesheet" href="https://cdn.anychart.com/releases/v8/css/anychart-ui.min.css" />
            <link rel="stylesheet" href="https://cdn.anychart.com/releases/v8/fonts/css/anychart.min.css" />
            <link rel="stylesheet" href="style1.css">
            <link rel="stylesheet" href="listing.css">
            
        </head>
        <body>
            <nav class="navtop">
                <div class="navcontainer">
                    <img src="/views/image2.png" alt="M.A.N.A Logo" class="logo">
                    <a href="index.php"><i class="fas fa-home"></i>Home</a>
                    <a href="listing.php"><i class="fas fa-address-book"></i>Données</a>
                    <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i>Déconnexion</a>
                </div>
            </nav>
    EOT;
    }

    
    function template_footer() {
            echo <<<EOT
        </body>
    </html>
    EOT;
    }

 ?>

