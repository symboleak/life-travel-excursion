<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>{site_name} - Votre excursion vous attend</title>
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
        .tips {
            background-color: #fff8e1;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #ffca28;
        }
        .tips h3 {
            margin-top: 0;
            color: #f57c00;
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
            .tips {
                background-color: #3a3020;
                border-left-color: #f57c00;
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
            
            <p>Nous avons remarqué que vous avez laissé une réservation d'excursion dans votre panier. Les meilleures aventures attendent souvent ceux qui font le premier pas !</p>
            
            <div class="cart-info">
                <h3>Votre réservation en attente</h3>
                
                {cart_items}
                
                <p><strong>Total : {cart_total}</strong></p>
            </div>
            
            <div style="text-align: center;">
                <a href="{recovery_link}" class="button">Compléter ma réservation</a>
            </div>
            
            <div class="tips">
                <h3>Pourquoi choisir nos excursions ?</h3>
                <ul>
                    <li><strong>Guides locaux experts</strong> connaissant parfaitement les destinations</li>
                    <li><strong>Petits groupes</strong> pour une expérience personnalisée</li>
                    <li><strong>Annulation gratuite</strong> jusqu'à 48h avant le départ</li>
                    <li><strong>Satisfaction garantie</strong> ou remboursement</li>
                </ul>
            </div>
            
            <p>Si vous avez des questions ou besoin d'assistance pour finaliser votre réservation, n'hésitez pas à nous contacter. Notre équipe se fera un plaisir de vous aider.</p>
            
            <p class="notice">Ce lien de récupération expirera dans <span class="highlight">{expiry_days} jours</span>. Après cette date, votre réservation pourrait ne plus être disponible aux mêmes conditions.</p>
        </div>
        
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> {site_name}. Tous droits réservés.</p>
            <p>Cet email vous a été envoyé car vous avez initié une réservation sur notre site. Si vous n'êtes pas à l'origine de cette action, veuillez ignorer cet email.</p>
        </div>
    </div>
</body>
</html>
