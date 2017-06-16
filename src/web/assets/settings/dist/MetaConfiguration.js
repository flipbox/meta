(function ($) {
    /** global: Craft */
    /** global: Garnish */
    /**
     * Meta configuration class
     */
    Craft.MetaConfiguration = Garnish.Base.extend(
        {
            fieldTypeInfo: null,

            inputNamePrefix: null,
            inputIdPrefix: null,

            $container: null,

            $fieldsColumnContainer: null,
            $fieldSettingsColumnContainer: null,

            $fieldItemsOuterContainer: null,
            $fieldSettingItemsContainer: null,

            $newFieldBtn: null,


            id: null,
            errors: null,

            $item: null,
            $nameLabel: null,
            $handleLabel: null,
            $nameHiddenInput: null,
            $handleHiddenInput: null,
            $fieldItemsContainer: null,

            fields: null,
            selectedField: null,
            fieldSort: null,
            totalNewFields: 0,
            fieldSettings: null,

            init: function (fieldTypeInfo, inputNamePrefix) {
                this.fieldTypeInfo = fieldTypeInfo;

                this.inputNamePrefix = inputNamePrefix;
                this.inputIdPrefix = Craft.formatInputId(this.inputNamePrefix);

                this.$container = $('#' + this.inputIdPrefix + '-meta-configuration:first .input:first');

                this.$fieldsColumnContainer = this.$container.children('.fields').children();
                this.$fieldSettingsColumnContainer = this.$container.children('.field-settings').children();

                this.$fieldItemsOuterContainer = this.$fieldsColumnContainer.children('.items');
                this.$fieldItemsContainer = this.$fieldItemsOuterContainer.children('.fields');
                this.$fieldSettingItemsContainer = this.$fieldSettingsColumnContainer.children('.items');

                this.setContainerHeight();

                this.$newFieldBtn = this.$fieldItemsOuterContainer.children('.btn');

                this.fields = {};

                var $fieldItems = this.$fieldItemsContainer.children();

                for (var i = 0; i < $fieldItems.length; i++) {
                    var $item = $($fieldItems[i]),
                        id = $item.data('id');

                    this.fields[id] = new Field(this, $item);

                    // Is this a new element?
                    var newMatch = (typeof id == 'string' && id.match(/new(\d+)/));

                    if (newMatch && newMatch[1] > this.totalNewFields) {
                        this.totalNewFields = parseInt(newMatch[1]);
                    }

                    // Auto select first field
                    if (i == 0 && !this.selectedField) {
                        this.fields[id].select();
                    }

                }

                this.fieldSort = new Garnish.DragSort($fieldItems, {
                    handle: '.move',
                    axis: 'y',
                    onSortChange: $.proxy(function () {
                        // Adjust the field setting containers to match the new sort order
                        for (var i = 0; i < this.fieldSort.$items.length; i++) {
                            var $item = $(this.fieldSort.$items[i]),
                                id = $item.data('id'),
                                field = this.fields[id];

                            field.$fieldSettingsContainer.appendTo(this.$fieldSettingItemsContainer);
                        }
                    }, this)
                });

                this.addListener(this.$newFieldBtn, 'click', 'addField');

                this.addListener(this.$fieldsColumnContainer, 'resize', 'setContainerHeight');
                this.addListener(this.$fieldSettingsColumnContainer, 'resize', 'setContainerHeight');
            },

            setContainerHeight: function () {
                setTimeout($.proxy(function () {
                    var maxColHeight = Math.max(this.$fieldsColumnContainer.height(), this.$fieldSettingsColumnContainer.height(), 400);
                    this.$container.height(maxColHeight);
                }, this), 1);
            },

            getFieldTypeInfo: function (type) {
                for (var i = 0; i < this.fieldTypeInfo.length; i++) {
                    if (this.fieldTypeInfo[i].type == type) {
                        return this.fieldTypeInfo[i];
                    }
                }
            },

            select: function () {
                if (this.selectedField == this) {
                    return;
                }

                if (this.selectedField) {
                    this.selectedField.deselect();
                }

                this.$fieldsColumnContainer.removeClass('hidden').trigger('resize');
                this.$fieldItemsContainer.removeClass('hidden');
                // this.$item.addClass('sel');
                this.selectedField = this;
            },

            deselect: function () {
                this.$fieldsColumnContainer.addClass('hidden').trigger('resize');
                this.$fieldItemsContainer.addClass('hidden');
                this.$fieldSettingsContainer.addClass('hidden');
                this.selectedField = null;

                if (this.selectedField) {
                    this.selectedField.deselect();
                }
            },

            addField: function () {
                this.totalNewFields++;
                var id = 'new' + this.totalNewFields;

                var $item = $(
                    '<div class="matrixconfigitem mci-field" data-id="' + id + '">' +
                    '<div class="name"><em class="light">' + Craft.t('app', '(blank)') + '</em>&nbsp;</div>' +
                    '<div class="handle code">&nbsp;</div>' +
                    '<div class="actions">' +
                    '<a class="move icon" title="' + Craft.t('app', 'Reorder') + '"></a>' +
                    '</div>' +
                    '</div>'
                ).appendTo(this.$fieldItemsContainer);

                this.fields[id] = new Field(this, $item);
                this.fields[id].select();

                this.fieldSort.addItems($item);
            }

        });

    var Field = Garnish.Base.extend(
        {
            configuration: null,
            id: null,

            inputNamePrefix: null,
            inputIdPrefix: null,

            selectedFieldType: null,
            initializedFieldTypeSettings: null,

            $item: null,
            $nameLabel: null,
            $handleLabel: null,

            $fieldSettingsContainer: null,
            $nameInput: null,
            $handleInput: null,
            $requiredCheckbox: null,
            $typeSelect: null,
            $translationSettingsContainer: null,
            $typeSettingsContainer: null,
            $deleteBtn: null,

            init: function (configuration, $item) {
                this.configuration = configuration;
                this.$item = $item;
                this.id = this.$item.data('id');

                this.inputNamePrefix = this.configuration.inputNamePrefix + '[fields][' + this.id + ']';
                this.inputIdPrefix = this.configuration.inputIdPrefix + '-fields-' + this.id;

                this.initializedFieldTypeSettings = {};

                this.$nameLabel = this.$item.children('.name');
                this.$handleLabel = this.$item.children('.handle');

                this.$fieldSettingsContainer = this.configuration.$fieldSettingItemsContainer.children('[data-id="' + this.id + '"]:first');

                var isNew = (!this.$fieldSettingsContainer.length);

                if (isNew) {
                    this.$fieldSettingsContainer = this.getDefaultFieldSettings().appendTo(this.configuration.$fieldSettingItemsContainer);
                }

                this.$nameInput = $('#' + this.inputIdPrefix + '-name');
                this.$handleInput = $('#' + this.inputIdPrefix + '-handle');
                this.$requiredCheckbox = $('#' + this.inputIdPrefix + '-required');
                this.$typeSelect = $('#' + this.inputIdPrefix + '-type');
                this.$translationSettingsContainer = $('#' + this.inputIdPrefix + '-translation-settings');
                this.$typeSettingsContainer = this.$fieldSettingsContainer.children('.fieldtype-settings:first');
                this.$deleteBtn = this.$fieldSettingsContainer.children('a.delete:first');

                if (isNew) {
                    this.setFieldType('craft\\fields\\PlainText');
                }
                else {
                    this.selectedFieldType = this.$typeSelect.val();
                    this.initializedFieldTypeSettings[this.selectedFieldType] = this.$typeSettingsContainer.children();
                }

                if (!this.$handleInput.val()) {
                    new Craft.HandleGenerator(this.$nameInput, this.$handleInput);
                }

                this.addListener(this.$item, 'click', 'select');
                this.addListener(this.$nameInput, 'textchange', 'updateNameLabel');
                this.addListener(this.$handleInput, 'textchange', 'updateHandleLabel');
                this.addListener(this.$requiredCheckbox, 'change', 'updateRequiredIcon');
                this.addListener(this.$typeSelect, 'change', 'onTypeSelectChange');
                this.addListener(this.$deleteBtn, 'click', 'confirmDelete');
            },

            select: function () {
                if (this.configuration.selectedField == this) {
                    return;
                }

                if (this.configuration.selectedField) {
                    this.configuration.selectedField.deselect();
                }

                this.configuration.$fieldSettingsColumnContainer.removeClass('hidden').trigger('resize');
                // this.configuration.$fieldSettingsContainer.removeClass('hidden');
                this.$fieldSettingsContainer.removeClass('hidden');
                this.$item.addClass('sel');
                this.configuration.selectedField = this;

                if (!Garnish.isMobileBrowser()) {
                    setTimeout($.proxy(function () {
                        this.$nameInput.focus();
                    }, this), 100);
                }
            },

            deselect: function () {
                this.$item.removeClass('sel');
                this.configuration.$fieldSettingsColumnContainer.addClass('hidden').trigger('resize');
                this.$fieldSettingsContainer.addClass('hidden');
                this.configuration.selectedField = null;
            },

            updateNameLabel: function () {
                var val = this.$nameInput.val();
                this.$nameLabel.html((val ? Craft.escapeHtml(val) : '<em class="light">' + Craft.t('app', '(blank)') + '</em>') + '&nbsp;');
            },

            updateHandleLabel: function () {
                this.$handleLabel.html(Craft.escapeHtml(this.$handleInput.val()) + '&nbsp;');
            },

            updateRequiredIcon: function () {
                if (this.$requiredCheckbox.prop('checked')) {
                    this.$nameLabel.addClass('required');
                }
                else {
                    this.$nameLabel.removeClass('required');
                }
            },

            onTypeSelectChange: function () {
                this.setFieldType(this.$typeSelect.val());
            },

            setFieldType: function (type) {
                // Show or hide the translation settings depending on if this type has content
                if ($.inArray(type, Craft.fieldTypesWithContent) != -1) {
                    this.$translationSettingsContainer.removeClass('hidden');
                } else {
                    this.$translationSettingsContainer.addClass('hidden');
                }

                if (this.selectedFieldType) {
                    this.initializedFieldTypeSettings[this.selectedFieldType].detach();
                }

                this.selectedFieldType = type;
                this.$typeSelect.val(type);

                var firstTime = (this.initializedFieldTypeSettings[type] === undefined),
                    $body,
                    footHtml;

                if (firstTime) {
                    var info = this.configuration.getFieldTypeInfo(type),
                        bodyHtml = this.getParsedFieldTypeHtml(info.settingsBodyHtml);

                    footHtml = this.getParsedFieldTypeHtml(info.settingsFootHtml);
                    $body = $('<div>' + bodyHtml + '</div>');

                    this.initializedFieldTypeSettings[type] = $body;
                }
                else {
                    $body = this.initializedFieldTypeSettings[type];
                }

                $body.appendTo(this.$typeSettingsContainer);

                if (firstTime) {
                    Craft.initUiElements($body);
                    Garnish.$bod.append(footHtml);
                }

                // Firefox might have been sleeping on the job.
                this.$typeSettingsContainer.trigger('resize');
            },

            getParsedFieldTypeHtml: function (html) {
                if (typeof html == 'string') {
                    html = html.replace(/__META_FIELD__/g, this.id);
                }
                else {
                    html = '';
                }

                return html;
            },

            getDefaultFieldSettings: function () {
                var $container = $('<div/>', {
                    'data-id': this.id
                });

                Craft.ui.createTextField({
                    label: Craft.t('app', 'Name'),
                    id: this.inputIdPrefix + '-name',
                    name: this.inputNamePrefix + '[name]'
                }).appendTo($container);

                Craft.ui.createTextField({
                    label: Craft.t('app', 'Handle'),
                    id: this.inputIdPrefix + '-handle',
                    'class': 'code',
                    name: this.inputNamePrefix + '[handle]',
                    maxlength: 64,
                    required: true
                }).appendTo($container);

                Craft.ui.createTextareaField({
                    label: Craft.t('app', 'Instructions'),
                    id: this.inputIdPrefix + '-instructions',
                    'class': 'nicetext',
                    name: this.inputNamePrefix + '[instructions]',
                    maxlength: 64
                }).appendTo($container);

                Craft.ui.createCheckboxField({
                    label: Craft.t('app', 'This field is required'),
                    id: this.inputIdPrefix + '-required',
                    name: this.inputNamePrefix + '[required]'
                }).appendTo($container);

                var fieldTypeOptions = [];

                for (var i = 0; i < this.configuration.fieldTypeInfo.length; i++) {
                    fieldTypeOptions.push({
                        value: this.configuration.fieldTypeInfo[i].type,
                        label: this.configuration.fieldTypeInfo[i].name
                    });
                }

                Craft.ui.createSelectField({
                    label: Craft.t('app', 'Field Type'),
                    id: this.inputIdPrefix + '-type',
                    name: this.inputNamePrefix + '[type]',
                    options: fieldTypeOptions,
                    value: 'craft\\fields\\PlainText'
                }).appendTo($container);

                if (Craft.isMultiSite) {
                    var $translationSettingsContainer = $('<div/>', {
                        id: this.inputIdPrefix + '-translation-settings'
                    }).appendTo($container);

                    Craft.ui.createSelectField({
                        label: Craft.t('app', 'Translation Method'),
                        id: this.inputIdPrefix + '-translation-method',
                        name: this.inputNamePrefix + '[translationMethod]',
                        options: [
                            {value: 'none', label: Craft.t('app', 'Not translatable')},
                            {value: 'language', label: Craft.t('app', 'Translate for each language')},
                            {value: 'site', label: Craft.t('app', 'Translate for each site')},
                            {value: 'custom', label: Craft.t('app', 'Custom…')}
                        ],
                        value: 'none',
                        toggle: true,
                        targetPrefix: this.inputIdPrefix + '-translation-method-'
                    }).appendTo($translationSettingsContainer);

                    var $translationKeyFormatContainer = $('<div/>', {
                        id: this.inputIdPrefix + '-translation-method-custom',
                        'class': 'hidden'
                    }).appendTo($translationSettingsContainer);

                    Craft.ui.createTextField({
                        label: Craft.t('app', 'Translation Key Format'),
                        id: this.inputIdPrefix + '-translation-key-format',
                        name: this.inputNamePrefix + '[translationKeyFormat]'
                    }).appendTo($translationKeyFormatContainer);
                }

                $('<hr/>').appendTo($container);

                $('<div/>', {
                    'class': 'fieldtype-settings'
                }).appendTo($container);

                $('<hr/>').appendTo($container);

                $('<a/>', {
                    'class': 'error delete',
                    text: Craft.t('app', 'Delete')
                }).appendTo($container);

                return $container;
            },

            confirmDelete: function () {
                if (confirm(Craft.t('app', 'Are you sure you want to delete this field?'))) {
                    this.selfDestruct();
                }
            },

            selfDestruct: function () {
                this.deselect();
                this.$item.remove();
                this.$fieldSettingsContainer.remove();

                this.configuration.fields[this.id] = null;
                delete this.configuration.fields[this.id];
            }

        });
})(jQuery);
