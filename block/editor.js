( function( blocks, element, blockEditor ) {
    var el = element.createElement;
    var RichText = blockEditor.RichText;

    console.log('Il file editor.js è stato caricato correttamente.');

    blocks.registerBlockType( 'rcb/recaptcha-content-block', {
        title: 'reCAPTCHA Content Protection',
        icon: 'shield',
        category: 'common',
        attributes: {
            content: {
                type: 'string',
            },
        },
        edit: function( props ) {
            var content = props.attributes.content;
            var setAttributes = props.setAttributes;
            var className = props.className;

            function onChangeContent( newContent ) {
                setAttributes( { content: newContent } );
            }

            return el(
                'div',
                { className: className },
                el(
                    RichText,
                    {
                        tagName: 'div',
                        placeholder: 'Inserisci il contenuto protetto...',
                        value: content,
                        onChange: onChangeContent,
                    }
                )
            );
        },
        save: function() {
            return null; // Il contenuto viene renderizzato lato server
        },
    } );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor );
