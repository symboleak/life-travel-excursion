<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>{site_name} - Sécurisez votre réservation d'excursion</title>
    <style type="text/css">
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #ffffff;
        }
        .header {
            text-align: center;
            padding: 20px 0;
            border-bottom: 3px solid #2a3950;
        }
        .header h1 {
            color: #2a3950;
            margin: 0;
            font-size: 28px;
        }
        .content {
            padding: 20px 0;
        }
        .content h2 {
            color: #2a3950;
            margin-top: 0;
        }
        .security-notice {
            background-color: #e8f5e9;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            border-left: 4px solid #4caf50;
        }
        .security-notice h3 {
            margin-top: 0;
            color: #2e7d32;
        }
        .cart-info {
            background-color: #f9f9f9;
            border-radius: 5px;
            padding: 20px;
            margin: 20px 0;
        }
        .button {
            display: inline-block;
            background-color: #2a3950;
            color: white !important;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            margin: 20px 0;
            text-align: center;
        }
        .button:hover {
            background-color: #1e2b3d;
        }
        .cart-items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .cart-items-table th {
            text-align: left;
            padding: 10px;
            border-bottom: 2px solid #ddd;
            background-color: #f2f2f2;
        }
        .cart-items-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .cart-items-table .price {
            text-align: right;
        }
        .cart-items-table .quantity {
            text-align: center;
        }
        .footer {
            text-align: center;
            font-size: 12px;
            color: #777;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .notice {
            font-size: 13px;
            color: #777;
            margin-top: 20px;
            font-style: italic;
        }
        .highlight {
            color: #2a3950;
            font-weight: bold;
        }
        .security-tips {
            background-color: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #2196f3;
        }
        .security-tips h3 {
            margin-top: 0;
            color: #0d47a1;
        }
        .verification-code {
            font-family: monospace;
            font-size: 18px;
            background-color: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            letter-spacing: 2px;
            text-align: center;
            margin: 20px 0;
            border: 1px dashed #ccc;
        }
        @media (prefers-color-scheme: dark) {
            body {
                background-color: #222;
                color: #eee;
            }
            .container {
                background-color: #333;
            }
            .header {
                border-bottom-color: #4a5970;
            }
            .header h1, .content h2 {
                color: #8fa4c4;
            }
            .security-notice {
                background-color: #1b3a1d;
                border-left-color: #4caf50;
            }
            .security-notice h3 {
                color: #81c784;
            }
            .cart-info {
                background-color: #3a3a3a;
            }
            .cart-items-table th {
                background-color: #3a3a3a;
                border-bottom-color: #444;
            }
            .cart-items-table td {
                border-bottom-color: #444;
            }
            .footer {
                color: #999;
                border-top-color: #444;
            }
            .notice {
                color: #999;
            }
            .highlight {
                color: #8fa4c4;
            }
            .security-tips {
                background-color: #0d2137;
                border-left-color: #2196f3;
            }
            .security-tips h3 {
                color: #64b5f6;
            }
            .verification-code {
                background-color: #2a2a2a;
                border-color: #444;
                color: #fff;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{site_name}</h1>
        </div>
        
        <div class="content">
            <h2>Bonjour {customer_first_name},</h2>
            
            <div class="security-notice">
                <h3>Transaction sécurisée</h3>
                <p>Vous avez récemment commencé une réservation d'excursion sur notre plateforme. Pour assurer la sécurité de votre réservation et garantir votre place, nous vous invitons à finaliser votre paiement.</p>
            </div>
            
            <p>Voici le récapitulatif de votre réservation en attente :</p>
            
            <div class="cart-info">                
                {cart_items}
                
                <p><strong>Total : {cart_total}</strong></p>
            </div>
            
            <div style="text-align: center;">
                <a href="{recovery_link}" class="button">Finaliser ma réservation en toute sécurité</a>
            </div>
            
            <div class="security-tips">
                <h3>Conseils de sécurité</h3>
                <ul>
                    <li>Assurez-vous que l'URL commence par <strong>https://</strong> avant de saisir vos informations de paiement</li>
                    <li>Notre site n'enregistre jamais vos données de carte complètes</li>
                    <li>Nous ne vous demanderons jamais vos identifiants par email</li>
                    <li>En cas de problème de connexion, utilisez notre fonction de récupération hors ligne</li>
                </ul>
            </div>
            
            <p>Si ce n'est pas vous qui avez initié cette réservation ou si vous avez des questions sur la sécurité de votre compte, veuillez contacter immédiatement notre service client.</p>
            
            <p class="notice">Ce lien de récupération sécurisé expirera dans <span class="highlight">{expiry_days} jours</span> pour des raisons de sécurité. Après cette date, vous devrez recommencer le processus de réservation.</p>
        </div>
        
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> {site_name}. Tous droits réservés.</p>
            <p>Si vous n'avez pas initié cette réservation, vous pouvez ignorer cet email en toute sécurité.</p>
            <p>Veuillez ne pas répondre à cet email automatique. Pour nous contacter, utilisez le formulaire sur notre site web.</p>
        </div>
    </div>
</body>
</html>
