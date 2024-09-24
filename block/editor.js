( function( blocks, element, blockEditor ) {
    var el = element.createElement;
    var RichText = blockEditor.RichText;
    var InnerBlocks = blockEditor.InnerBlocks;
    var InspectorControls = blockEditor.InspectorControls;
    var TextControl = wp.components.TextControl;

    blocks.registerBlockType( 'rcb/recaptcha-content-block', {
        title: 'Sezione reCAPTCHA',
        icon: 'shield',
        category: 'common',
        attributes: {
            content: {
                type: 'string',
            },
            buttonText: {
                type: 'string',
                default: 'Mostra il contenuto'
            },
        },
        edit: function( props ) {
            var content = props.attributes.content;
            var buttonText = props.attributes.buttonText;
            var setAttributes = props.setAttributes;
            var className = props.className;

            function onChangeButtonText( newText ) {
                setAttributes( { buttonText: newText } );
            }

            return el(
                'div',
                { className: className },
                el(
                    InspectorControls,
                    {},
                    el(
                        TextControl,
                        {
                            label: 'Testo del Pulsante',
                            value: buttonText,
                            onChange: onChangeButtonText,
                        }
                    )
                ),
                el(
                    'div',
                    { className: 'rcb-button' },
                    el( RichText, {
                        tagName: 'button',
                        value: buttonText,
                        onChange: onChangeButtonText,
                    })
                ),
                el(
                    'div',
                    { className: 'rcb-protected-content' },
                    el( InnerBlocks )
                )
            );
        },
        save: function() {
            return el( 'div', {}, el( InnerBlocks.Content ) );
        },
    } );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor );
