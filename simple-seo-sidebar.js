/**
 * simple-seo-sidebar.js
 * Block editor sidebar for Simple SEO plugin.
 */

( function( wp ) {
    const { registerPlugin } = wp.plugins;
    const { PluginDocumentSettingPanel } = wp.editPost;
    const { TextControl, TextareaControl, SelectControl } = wp.components;
    const { useSelect, useDispatch } = wp.data;
    const { __, _n, sprintf } = wp.i18n;
    const { createElement: el } = wp.element;

    function SimpleSEOPlugin() {
        const meta = useSelect(
            select => select( 'core/editor' ).getEditedPostAttribute( 'meta' ),
            []
        );
        const { editPost } = useDispatch( 'core/editor' );

        const {
            simple_seo_seo_title = '',
            simple_seo_seo_description = '',
            simple_seo_seo_robots = '',
            simple_seo_seo_canonical = '',
        } = meta;

        function onChangeMeta( key, value ) {
            editPost( { meta: { ...meta, [ key ]: value } } );
        }

        // Character count for description,
        const descCount = simple_seo_seo_description.length;

        return el(
            PluginDocumentSettingPanel,
            {
                name: 'simple-seo',
                title: __( 'Simple SEO', 'simple-seo' ),
                initialOpen: true,
            },

            // SEO Title.
            el( TextControl, {
                label: __( 'SEO Title', 'simple-seo' ),
                value: simple_seo_seo_title,
                onChange: value => onChangeMeta( 'simple_seo_seo_title', value ),
            } ),

            // Meta Description.
            el( TextareaControl, {
                label: __( 'Meta Description', 'simple-seo' ),
                value: simple_seo_seo_description,
                onChange: value => onChangeMeta( 'simple_seo_seo_description', value ),
            } ),

            // Live character counter.
            el(
                'p',
                { style: { marginTop: '4px', fontSize: '12px', color: '#666' } },
                sprintf(
                    _n(
                        '%s character',
                        '%s characters',
                        descCount,
                        'simple-seo'
                    ),
                    descCount
                )
            ),

            // Robots dropdown.
            el( SelectControl, {
                label: __( 'Robots', 'simple-seo' ),
                value: simple_seo_seo_robots,
                options: [
                    { label: __( 'Index, Follow',   'simple-seo' ), value: 'index,follow' },
                    { label: __( 'Noindex, Follow', 'simple-seo' ), value: 'noindex,follow' },
                    { label: __( 'Index, Nofollow', 'simple-seo' ), value: 'index,nofollow' },
                    { label: __( 'Noindex, Nofollow','simple-seo' ), value: 'noindex,nofollow' },
                ],
                onChange: value => onChangeMeta( 'simple_seo_seo_robots', value ),
            } ),

            // Canonical URL.
            el( TextControl, {
                label: __( 'Canonical URL', 'simple-seo' ),
                type: 'url',
                value: simple_seo_seo_canonical,
                onChange: value => onChangeMeta( 'simple_seo_seo_canonical', value ),
            } )
        );
    }

    registerPlugin( 'simple-seo', { render: SimpleSEOPlugin } );
} )( window.wp );
