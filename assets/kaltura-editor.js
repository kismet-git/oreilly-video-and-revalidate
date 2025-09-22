( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { __ } = wp.i18n;
	const { InspectorControls, useBlockProps } = wp.blockEditor || wp.editor;
	const { PanelBody, TextControl, ToggleControl } = wp.components;

	registerBlockType( 'oreilly/kaltura-video', {
		edit( { attributes, setAttributes } ) {
			const { partnerId, entryId, poster, autoplay, consentRequired } = attributes;
			const blockProps = useBlockProps( { className: 'oreilly-kaltura-editor' } );

			return (
				wp.element.createElement( wp.element.Fragment, null,
					wp.element.createElement( InspectorControls, null,
						wp.element.createElement( PanelBody, { title: __( 'Kaltura Settings', 'oreilly' ), initialOpen: true },
							wp.element.createElement( TextControl, { label: 'Partner ID', value: partnerId || '', onChange: (v)=> setAttributes( { partnerId: v } ) }),
							wp.element.createElement( TextControl, { label: 'Entry ID', value: entryId || '', onChange: (v)=> setAttributes( { entryId: v } ) }),
							wp.element.createElement( TextControl, { label: 'Poster URL', value: poster || '', onChange: (v)=> setAttributes( { poster: v } ) }),
							wp.element.createElement( ToggleControl, { label: 'Autoplay', checked: !!autoplay, onChange: (v)=> setAttributes( { autoplay: !!v } ) }),
							wp.element.createElement( ToggleControl, { label: 'Consent required', checked: !!consentRequired, onChange: (v)=> setAttributes( { consentRequired: !!v } ) })
						)
					),
					wp.element.createElement( 'div', blockProps,
						wp.element.createElement( 'div', {
							className: 'oreilly-kaltura-preview',
							style: { aspectRatio:'16/9', background:'#111', position:'relative', display:'grid', placeItems:'center', color:'#fff' }
						},
							poster
								? wp.element.createElement( 'img', { src: poster, alt: '', style: { position:'absolute', inset:0, width:'100%', height:'100%', objectFit:'cover', opacity:0.7 } } )
								: null,
							wp.element.createElement( 'div', { style: { position:'relative', padding:'0.75rem 1rem', background:'#0009', borderRadius:'6px' } },
								`Kaltura preview — partnerId=${partnerId||'—'} entryId=${entryId||'—'}`
							)
						)
					)
				)
			);
		},
		save() { return null; }
	} );
} )( window.wp );
