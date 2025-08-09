/**
 * Script pour l'éditeur de modèles de notification
 */
(function($) {
    'use strict';
    
    // Variables globales
    var editor = null;
    
    // Initialisation
    $(document).ready(function() {
        // Initialiser l'éditeur
        initializeEditor();
        
        // Gérer les clics sur les variables
        handleVariableInsertion();
        
        // Gérer les boutons d'action
        initializeButtons();
    });
    
    /**
     * Initialise l'éditeur de code
     */
    function initializeEditor() {
        var $textarea = $('#template-content');
        
        // Si c'est un sujet d'email (input text), ne pas initialiser CodeMirror
        if ($textarea.closest('.lte-subject-editor').length > 0) {
            return;
        }
        
        // Déterminer le mode d'édition
        var editorMode = $textarea.data('editor-mode') || 'text';
        var editorSettings = wp.codeEditor.defaultSettings ? _.clone(wp.codeEditor.defaultSettings) : {};
        
        // Configurer l'éditeur selon le mode
        editorSettings.codemirror = _.extend(
            {},
            editorSettings.codemirror,
            {
                mode: editorMode === 'html' ? 'htmlmixed' : 'text/plain',
                lineNumbers: true,
                lineWrapping: true,
                indentUnit: 2,
                tabSize: 2,
                autoCloseTags: editorMode === 'html',
                autoCloseBrackets: true,
                matchBrackets: true,
                continueComments: true,
                extraKeys: {
                    'Ctrl-Space': 'autocomplete',
                    'Ctrl-/': 'toggleComment',
                    'Cmd-/': 'toggleComment',
                    'Alt-F': 'findPersistent',
                    'Ctrl-F': 'findPersistent'
                }
            }
        );
        
        // Initialiser CodeMirror
        editor = wp.codeEditor.initialize($textarea, editorSettings);
        
        // Focus l'éditeur
        setTimeout(function() {
            editor.codemirror.refresh();
            editor.codemirror.focus();
        }, 100);
    }
    
    /**
     * Gère l'insertion des variables dans l'éditeur
     */
    function handleVariableInsertion() {
        $('.lte-variable-item').on('click', function() {
            var variable = $(this).data('variable');
            
            if (!variable) {
                return;
            }
            
            // Si on utilise CodeMirror
            if (editor && editor.codemirror) {
                var cm = editor.codemirror;
                cm.replaceSelection(variable);
                cm.focus();
            } 
            // Pour les champs input (sujet d'email)
            else {
                var $input = $('#template-content');
                var currentPos = $input[0].selectionStart;
                var currentValue = $input.val();
                var newValue = currentValue.substring(0, currentPos) + variable + currentValue.substring(currentPos);
                
                $input.val(newValue);
                $input.focus();
                $input[0].selectionStart = $input[0].selectionEnd = currentPos + variable.length;
            }
        });
    }
    
    /**
     * Initialise les boutons d'action
     */
    function initializeButtons() {
        // Bouton de prévisualisation
        $('#lte-preview-button').on('click', function() {
            previewTemplate();
        });
        
        // Bouton de sauvegarde
        $('#lte-save-button').on('click', function() {
            saveTemplate();
        });
        
        // Bouton de réinitialisation
        $('#lte-reset-button').on('click', function() {
            confirmResetTemplate();
        });
    }
    
    /**
     * Génère une prévisualisation du modèle
     */
    function previewTemplate() {
        var $previewPanel = $('#lte-preview-panel');
        var $previewContent = $('#lte-preview-content');
        var notificationType = $('#lte-template-form input[name="notification_type"]').val();
        var channel = $('#lte-template-form input[name="channel"]').val();
        var content = getEditorContent();
        
        if (!content) {
            $previewContent.html('<div class="lte-preview-placeholder">Le contenu du modèle est vide.</div>');
            return;
        }
        
        // Afficher l'état de chargement
        $previewPanel.addClass('lte-is-loading');
        $previewContent.html('<div class="lte-preview-placeholder">Génération de la prévisualisation...</div>');
        
        // Envoyer la requête AJAX
        $.ajax({
            url: lteTemplateEditor.ajaxUrl,
            type: 'POST',
            data: {
                action: 'lte_preview_template',
                nonce: lteTemplateEditor.previewNonce,
                content: content,
                type: notificationType,
                channel: channel
            },
            success: function(response) {
                $previewPanel.removeClass('lte-is-loading');
                
                if (response.success) {
                    // Channel-specific preview styling
                    if (channel === 'email') {
                        // Pour les emails, afficher directement le HTML
                        $previewContent.html(response.data.preview);
                    } else if (channel === 'subject') {
                        // Pour les sujets, afficher dans un conteneur spécial
                        $previewContent.html('<div class="lte-subject-preview">' + response.data.preview + '</div>');
                    } else {
                        // Pour SMS et WhatsApp, formater avec des sauts de ligne
                        var formattedPreview = response.data.preview.replace(/\n/g, '<br>');
                        $previewContent.html('<div class="lte-text-preview">' + formattedPreview + '</div>');
                    }
                } else {
                    $previewContent.html('<div class="lte-preview-error">' + lteTemplateEditor.previewError + '</div>');
                }
            },
            error: function() {
                $previewPanel.removeClass('lte-is-loading');
                $previewContent.html('<div class="lte-preview-error">' + lteTemplateEditor.previewError + '</div>');
            }
        });
    }
    
    /**
     * Sauvegarde le modèle
     */
    function saveTemplate() {
        var $saveButton = $('#lte-save-button');
        var $saveStatus = $('#lte-save-status');
        var notificationType = $('#lte-template-form input[name="notification_type"]').val();
        var channel = $('#lte-template-form input[name="channel"]').val();
        var content = getEditorContent();
        
        // Désactiver le bouton et afficher l'état de chargement
        $saveButton.prop('disabled', true).text('Enregistrement...');
        $saveStatus.text('').removeClass('lte-status-success lte-status-error');
        
        // Envoyer la requête AJAX
        $.ajax({
            url: lteTemplateEditor.ajaxUrl,
            type: 'POST',
            data: {
                action: 'lte_save_template',
                nonce: lteTemplateEditor.saveNonce,
                content: content,
                type: notificationType,
                channel: channel
            },
            success: function(response) {
                if (response.success) {
                    $saveStatus.text(lteTemplateEditor.saveSuccess).addClass('lte-status-success');
                    
                    // Faire disparaître le statut après 3 secondes
                    setTimeout(function() {
                        $saveStatus.text('').removeClass('lte-status-success');
                    }, 3000);
                } else {
                    $saveStatus.text(response.data.message || lteTemplateEditor.saveError).addClass('lte-status-error');
                }
            },
            error: function() {
                $saveStatus.text(lteTemplateEditor.saveError).addClass('lte-status-error');
            },
            complete: function() {
                // Réactiver le bouton
                $saveButton.prop('disabled', false).text('Enregistrer');
            }
        });
    }
    
    /**
     * Demande confirmation avant de réinitialiser le modèle
     */
    function confirmResetTemplate() {
        if (confirm('Êtes-vous sûr de vouloir réinitialiser ce modèle à sa valeur par défaut ? Cette action ne peut pas être annulée.')) {
            resetTemplate();
        }
    }
    
    /**
     * Réinitialise le modèle à sa valeur par défaut
     */
    function resetTemplate() {
        var defaultContent = $('#lte-default-content').text();
        
        // Si on utilise CodeMirror
        if (editor && editor.codemirror) {
            editor.codemirror.setValue(defaultContent);
        } 
        // Pour les champs input (sujet d'email)
        else {
            $('#template-content').val(defaultContent);
        }
        
        // Afficher un message de confirmation
        $('#lte-save-status').text('Modèle réinitialisé à sa valeur par défaut.').addClass('lte-status-success');
        
        // Faire disparaître le statut après 3 secondes
        setTimeout(function() {
            $('#lte-save-status').text('').removeClass('lte-status-success');
        }, 3000);
        
        // Prévisualiser le modèle réinitialisé
        previewTemplate();
    }
    
    /**
     * Récupère le contenu de l'éditeur
     */
    function getEditorContent() {
        // Si on utilise CodeMirror
        if (editor && editor.codemirror) {
            return editor.codemirror.getValue();
        }
        
        // Pour les champs input (sujet d'email)
        return $('#template-content').val();
    }
    
})(jQuery);
