const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const TerserPlugin = require('terser-webpack-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');
const { WebpackManifestPlugin } = require('webpack-manifest-plugin');

// Détection de l'environnement
const isDev = process.env.NODE_ENV === 'development';
const isProd = !isDev;

// Configuration de base commune à tous les environnements
const config = {
  // Mode de compilation (production par défaut, peut être surchargé par la ligne de commande)
  mode: isProd ? 'production' : 'development',
  
  // Points d'entrée de l'application
  entry: {
    // Service worker pour l'expérience offline
    'service-worker': path.resolve(__dirname, 'life-travel-sw.js'),
    
    // Scripts pour le frontend
    'script-optimized': path.resolve(__dirname, 'js/script-optimized.js'),
    
    // Scripts pour l'administration
    'admin-unified': path.resolve(__dirname, 'js/admin-unified.js'),
    
    // Styles CSS
    'frontend-unified': path.resolve(__dirname, 'assets/css/frontend-unified.css'),
    'admin-unified': path.resolve(__dirname, 'assets/css/admin-unified.css'),
  },
  
  // Configuration de sortie
  output: {
    filename: isProd ? '[name].[contenthash:8].js' : '[name].js',
    path: path.resolve(__dirname, 'dist'),
    publicPath: '/wp-content/plugins/life-travel-excursion/dist/',
    clean: true // Nettoie le dossier dist avant la génération
  },
  
  // Optimisation pour divers environnements
  optimization: {
    minimize: isProd,
    minimizer: [
      // Minification JavaScript avec Terser
      new TerserPlugin({
        terserOptions: {
          compress: {
            drop_console: isProd, // Supprime les console.log en production
            ecma: 2015,
            conditionals: true
          },
          format: {
            comments: false,
          },
        },
        extractComments: false,
      }),
      // Minification CSS
      new CssMinimizerPlugin({
        minimizerOptions: {
          preset: [
            'default',
            {
              discardComments: { removeAll: true },
              normalizeWhitespace: isProd
            },
          ],
        },
      }),
    ],
    // Séparation des chunks
    splitChunks: {
      cacheGroups: {
        // Regroupe les dépendances communes
        vendor: {
          test: /[\\/]node_modules[\\/]/,
          name: 'vendors',
          chunks: 'all',
        },
      },
    },
  },
  
  // Règles de traitement des modules
  module: {
    rules: [
      // Traitement des fichiers JavaScript
      {
        test: /\.js$/,
        exclude: /node_modules/,
        use: {
          loader: 'babel-loader',
          options: {
            presets: [
              ['@babel/preset-env', {
                useBuiltIns: 'usage',
                corejs: 3,
                // Ciblage des navigateurs, important pour le contexte camerounais
                targets: {
                  browsers: [
                    '> 1%', // Navigateurs avec plus de 1% de part de marché
                    'last 2 versions', // Deux dernières versions majeures
                    'not dead', // Navigateurs encore maintenus
                    'ie 11', // Support d'Internet Explorer 11 (encore utilisé dans certaines régions)
                    'Firefox ESR', // Support Firefox Extended Support Release
                    'Opera Mini' // Très utilisé en Afrique sur les appareils mobiles
                  ]
                }
              }]
            ],
            plugins: [
              '@babel/plugin-transform-runtime',
              '@babel/plugin-proposal-object-rest-spread'
            ]
          }
        }
      },
      // Traitement des fichiers CSS
      {
        test: /\.css$/,
        use: [
          MiniCssExtractPlugin.loader,
          {
            loader: 'css-loader',
            options: {
              sourceMap: isDev,
              importLoaders: 1
            }
          },
          {
            loader: 'postcss-loader',
            options: {
              postcssOptions: {
                plugins: [
                  'postcss-preset-env', // Ajoute les préfixes automatiquement
                  'autoprefixer',
                  isProd && 'cssnano' // Minifie le CSS en production
                ].filter(Boolean)
              }
            }
          }
        ]
      },
      // Traitement des images
      {
        test: /\.(png|jpe?g|gif|svg)$/i,
        type: 'asset',
        parser: {
          dataUrlCondition: {
            maxSize: 8 * 1024 // 8kb - les images plus petites seront incluses en base64
          }
        },
        generator: {
          filename: 'images/[name].[hash:8][ext]'
        }
      }
    ]
  },
  
  // Plugins
  plugins: [
    // Extraction des CSS en fichiers distincts
    new MiniCssExtractPlugin({
      filename: isProd ? 'css/[name].[contenthash:8].css' : 'css/[name].css',
      chunkFilename: isProd ? 'css/[id].[contenthash:8].css' : 'css/[id].css'
    }),
    
    // Génération d'un manifeste d'assets
    new WebpackManifestPlugin({
      fileName: 'asset-manifest.json',
      publicPath: '/wp-content/plugins/life-travel-excursion/dist/',
      generate: (seed, files) => {
        const manifestFiles = files.reduce((manifest, file) => {
          manifest[file.name] = file.path;
          return manifest;
        }, seed);

        return {
          files: manifestFiles,
          version: process.env.npm_package_version || '1.0.0'
        };
      }
    })
  ],
  
  // Configuration des sources maps
  devtool: isDev ? 'eval-source-map' : false,
  
  // Statistiques lors de la compilation
  stats: {
    colors: true,
    modules: false,
    children: false,
    chunks: false,
    chunkModules: false,
    errors: true,
    errorDetails: true,
  },
  
  // Configuration spécifique pour le contexte camerounais
  performance: {
    // Tailles limites adaptées aux connexions lentes
    maxAssetSize: 300 * 1024, // 300kb par asset maximum
    maxEntrypointSize: 500 * 1024, // 500kb par point d'entrée maximum
    hints: isProd ? 'warning' : false
  }
};

// Export de la configuration
module.exports = config;
