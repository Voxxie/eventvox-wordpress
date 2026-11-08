/* EventVOX blocks in the editor (class-blocks.php registers them and draws them on the server). No build step: plain
   wp.element calls. The lists show their real content (ServerSideRender); the iframes a placeholder. */
(function (wp, config) {
    'use strict';
    if (!wp || !config) return;
    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var PanelBody = wp.components.PanelBody;
    var TextControl = wp.components.TextControl;
    var TextareaControl = wp.components.TextareaControl;
    var Placeholder = wp.components.Placeholder;
    var ServerSideRender = wp.serverSideRender;

    config.blocks.forEach(function (block) {
        var attributes = {};
        Object.keys(block.attributes).forEach(function (key) {
            attributes[key] = { type: block.attributes[key].type, default: block.attributes[key].default };
        });

        wp.blocks.registerBlockType(block.name, {
            apiVersion: 2,
            title: block.title,
            description: block.description,
            category: 'widgets',
            icon: block.icon,
            keywords: ['eventvox'],
            attributes: attributes,
            supports: { html: false },
            edit: function (props) {
                var controls = Object.keys(block.attributes).map(function (key) {
                    var spec = block.attributes[key];
                    var common = {
                        key: key,
                        label: spec.label,
                        value: props.attributes[key] === undefined || props.attributes[key] === null ? '' : String(props.attributes[key]),
                        onChange: function (value) {
                            var update = {};
                            update[key] = spec.type === 'number' ? (parseInt(value, 10) || spec.default) : value;
                            props.setAttributes(update);
                        },
                    };
                    if (spec.multiline) return el(TextareaControl, common);
                    if (spec.type === 'number') common.type = 'number';
                    return el(TextControl, common);
                });
                var preview;
                if (!config.configured) {
                    preview = el(Placeholder, { icon: block.icon, label: block.title },
                        el('p', null, config.setupNote, ' ', el('a', { href: config.settingsUrl }, config.settingsTitle)));
                } else if (block.frame) {
                    preview = el(Placeholder, { icon: block.icon, label: block.title, instructions: config.frameNote });
                } else {
                    preview = el(ServerSideRender, { block: block.name, attributes: props.attributes });
                }
                return el(Fragment, null,
                    el(InspectorControls, null, el(PanelBody, { title: config.settingsTitle }, controls)),
                    el('div', useBlockProps(), preview));
            },
            save: function () {
                return null;
            },
        });
    });
})(window.wp, window.eventvoxBlocks);
