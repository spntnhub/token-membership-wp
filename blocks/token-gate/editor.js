/**
 * Token Gate — Gutenberg block editor script.
 *
 * Pure vanilla JS / createElement — no build step required.
 * WordPress auto-enqueues this on the block editor page.
 *
 * Block: token-membership/gate
 * Render: PHP render_callback (TM_Shortcode::render_block)
 * Content inside the gate is managed via InnerBlocks.
 */
(function () {
  'use strict';

  var el           = wp.element.createElement;
  var Fragment     = wp.element.Fragment;
  var __           = wp.i18n.__;
  var registerBlockType = wp.blocks.registerBlockType;
  var InspectorControls = wp.blockEditor.InspectorControls;
  var InnerBlocks       = wp.blockEditor.InnerBlocks;
  var PanelBody     = wp.components.PanelBody;
  var PanelRow      = wp.components.PanelRow;
  var TextControl   = wp.components.TextControl;
  var TextareaControl = wp.components.TextareaControl;
  var Notice        = wp.components.Notice;

  registerBlockType( 'token-membership/gate', {

    // ── Edit ────────────────────────────────────────────────────────────────
    edit: function ( props ) {
      var attrs      = props.attributes;
      var projectId  = attrs.projectId;
      var title      = attrs.title;
      var description = attrs.description;
      var setAttr    = props.setAttributes;

      return el(
        Fragment,
        null,

        // ── Inspector sidebar ──────────────────────────────────────────────
        el(
          InspectorControls,
          null,
          el(
            PanelBody,
            { title: __( 'Token Gate Settings', 'token-membership' ), initialOpen: true },

            el( PanelRow, null,
              el( TextControl, {
                label:    __( 'Project ID', 'token-membership' ),
                value:    projectId,
                onChange: function ( v ) { setAttr( { projectId: v } ); },
                help:     __( 'Your SPNTN project ID — find it in the dashboard.', 'token-membership' ),
              } )
            ),

            el( PanelRow, null,
              el( TextControl, {
                label:    __( 'Gate Title', 'token-membership' ),
                value:    title,
                onChange: function ( v ) { setAttr( { title: v } ); },
              } )
            ),

            el( PanelRow, null,
              el( TextareaControl, {
                label:    __( 'Gate Description', 'token-membership' ),
                value:    description,
                onChange: function ( v ) { setAttr( { description: v } ); },
                rows:     2,
              } )
            )
          )
        ),

        // ── Editor canvas preview ──────────────────────────────────────────
        el(
          'div',
          { className: 'tm-block-editor-wrap' },

          // Header
          el(
            'div',
            { className: 'tm-block-editor-header' },
            el( 'span', { className: 'dashicons dashicons-lock tm-block-lock-icon' } ),
            el( 'div', null,
              el( 'strong', null, title || __( 'Token Gate', 'token-membership' ) ),
              description
                ? el( 'p', { className: 'tm-block-editor-desc' }, description )
                : null
            )
          ),

          // Project ID notice / warning
          projectId
            ? el( 'code', { className: 'tm-block-editor-pid' },
                'project_id: ' + projectId )
            : el( Notice,
                { status: 'warning', isDismissible: false, className: 'tm-block-editor-notice' },
                __( 'Set a Project ID in the block settings (sidebar) to activate the gate.', 'token-membership' )
              ),

          // Inner content area (what's shown to members)
          el(
            'div',
            { className: 'tm-block-editor-content' },
            el( 'p', { className: 'tm-block-editor-content-label' },
              __( '🔓 Content visible to token holders:', 'token-membership' )
            ),
            el( InnerBlocks, null )
          )
        )
      );
    },

    // ── Save ─────────────────────────────────────────────────────────────────
    // Dynamic block — PHP render_callback handles frontend output.
    // InnerBlocks.Content stores child block markup in post_content.
    save: function () {
      return el( InnerBlocks.Content, null );
    },

  } );

} )();
