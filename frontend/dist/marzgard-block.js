/* ============================================================
   مرزگارد — Gutenberg block (dynamic, server-side rendered)
   ============================================================ */
(function () {
  'use strict';

  if (!window.wp || !wp.blocks || !wp.element) { return; }

  var el = wp.element.createElement;
  var __ = (wp.i18n && wp.i18n.__) ? wp.i18n.__ : function (s) { return s; };
  var InspectorControls = wp.blockEditor ? wp.blockEditor.InspectorControls : wp.editor.InspectorControls;
  var SelectControl = wp.components.SelectControl;
  var TextControl = wp.components.TextControl;
  var PanelBody = wp.components.PanelBody;

  wp.blocks.registerBlockType('marzgard/form', {
    title: __('مرزگارد — فرم ورود', 'marzgard'),
    description: __('فرم احراز هویت هدلس مرزگارد', 'marzgard'),
    icon: 'shield',
    category: 'widgets',
    supports: { align: false, html: false },

    attributes: {
      mode: { type: 'string', default: '' },
      redirect: { type: 'string', default: '' }
    },

    edit: function (props) {
      var attributes = props.attributes;
      var setAttributes = props.setAttributes;

      return el(
        'div',
        { className: 'marzgard-block-preview' },
        InspectorControls && el(
          InspectorControls,
          null,
          el(
            PanelBody,
            { title: __('تنظیمات مرزگارد', 'marzgard'), initialOpen: true },
            el(SelectControl, {
              label: __('حالت', 'marzgard'),
              value: attributes.mode,
              options: [
                { label: __('پیشفرض (تنظیمات عمومی)', 'marzgard'), value: '' },
                { label: __('ورود', 'marzgard'), value: 'login' },
                { label: __('ثبت نام', 'marzgard'), value: 'register' }
              ],
              onChange: function (value) { setAttributes({ mode: value }); }
            }),
            el(TextControl, {
              label: __('آدرس هدایت پس از ورود', 'marzgard'),
              value: attributes.redirect,
              placeholder: 'https://',
              onChange: function (value) { setAttributes({ redirect: value }); }
            })
          )
        ),
        el(
          'div',
          {
            style: {
              border: '1px dashed #009595',
              borderRadius: '12px',
              padding: '28px',
              textAlign: 'center',
              fontFamily: 'Arad, sans-serif',
              background: '#f0fbfb',
              color: '#006d6d'
            }
          },
          el('strong', null, '🛡 مرزگارد'),
          el('p', null, __('فرم ورود و ثبت نام مبتنی بر OTP در اینجا نمایش داده می شود.', 'marzgard'))
        )
      );
    },

    save: function () { return null; }
  });
})();
