( function( blocks, element, blockEditor, components, i18n ) {
    var el = element.createElement;
    var __ = i18n.__;
    var RichText = blockEditor.RichText;
    var InnerBlocks = blockEditor.InnerBlocks;
    var InspectorControls = blockEditor.InspectorControls;
    var TextControl = components.TextControl;
    var PanelBody = components.PanelBody;
    var SelectControl = components.SelectControl;

    blocks.registerBlockType( 'rcb/recaptcha-content-block', {
        title: __( 'Sezione Protetta CAPTCHA', 'rcb-recaptcha-block' ),
        icon: 'shield-alt',
        category: 'common',
        attributes: {
            buttonText: {
                type: 'string',
                default: __( 'Mostra il contenuto', 'rcb-recaptcha-block' ),
            },
            theme: {
                type: 'string',
                default: 'light',
            },
            mode: {
                type: 'string',
                default: 'normal',
            },
        },
        edit: function( props ) {
            var { buttonText, theme, mode } = props.attributes;
            var setAttributes = props.setAttributes;
            var className = props.className;

            function onChangeButtonText( newText ) {
                setAttributes( { buttonText: newText } );
            }
            function onChangeTheme( newTheme ) {
                setAttributes( { theme: newTheme } );
            }
            function onChangeMode( newMode ) {
                setAttributes( { mode: newMode } );
            }

            return el(
                'div',
                { className: className },
                el(
                    InspectorControls,
                    {},
                    el(
                        PanelBody,
                        { title: __( 'Impostazioni CAPTCHA', 'rcb-recaptcha-block' ) },
                        el(
                            TextControl,
                            {
                                label: __( 'Testo del Pulsante', 'rcb-recaptcha-block' ),
                                value: buttonText,
                                onChange: onChangeButtonText,
                            }
                        ),
                        el(
                            SelectControl,
                            {
                                label: __( 'Tema (reCAPTCHA/hCaptcha)', 'rcb-recaptcha-block' ),
                                value: theme,
                                options: [
                                    { label: __( 'Chiaro', 'rcb-recaptcha-block' ), value: 'light' },
                                    { label: __( 'Scuro', 'rcb-recaptcha-block' ), value: 'dark' },
                                ],
                                onChange: onChangeTheme,
                            }
                        ),
                        el(
                            SelectControl,
                            {
                                label: __( 'Modalità', 'rcb-recaptcha-block' ),
                                value: mode,
                                options: [
                                    { label: __( 'Normale (Visibile)', 'rcb-recaptcha-block' ), value: 'normal' },
                                    { label: __( 'Invisibile', 'rcb-recaptcha-block' ), value: 'invisible' },
                                ],
                                onChange: onChangeMode,
                                help: __( 'La modalità invisibile non mostra la sfida a meno che l\'utente non sia sospetto.', 'rcb-recaptcha-block' )
                            }
                        )
                    )
                ),
                el(
                    'div',
                    { className: 'rcb-button-preview' },
                    el( RichText, {
                        tagName: 'button',
                        value: buttonText,
                        onChange: onChangeButtonText,
                        withoutInteractiveFormatting: true,
                        allowedFormats: [],
                        placeholder: __( 'Testo pulsante...', 'rcb-recaptcha-block' ),
                    })
                ),
                el(
                    'div',
                    { className: 'rcb-protected-content-editor' },
                    el( InnerBlocks, {
                        renderAppender: InnerBlocks.ButtonBlockAppender,
                    } )
                )
            );
        },
        save: function() {
            return el( 'div', {}, el( InnerBlocks.Content ) );
        },
    } );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n );

