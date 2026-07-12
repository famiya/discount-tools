/**
 * Discount Tools - Condition Builder
 *
 * Handles dynamic condition building interface.
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/admin/js
 * @since      1.0.0
 */

(function($) {
    'use strict';

    /**
     * Condition Builder Class
     */
    var ConditionBuilder = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.updateAllOperators();
            this.updateGroupNumbers();
            this.initializeExistingSelect2();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;

            // Add condition
            $(document).on('click', '.dt-add-condition', function(e) {
                e.preventDefault();
                var $group = $(this).closest('.dt-condition-group');
                self.addCondition($group);
            });

            // Remove condition
            $(document).on('click', '.dt-remove-condition', function(e) {
                e.preventDefault();
                var $row = $(this).closest('.dt-condition-row');
                self.removeCondition($row);
            });

            // Add group
            $(document).on('click', '.dt-add-group', function(e) {
                e.preventDefault();
                self.addGroup();
            });

            // Remove group
            $(document).on('click', '.dt-remove-group', function(e) {
                e.preventDefault();
                var $group = $(this).closest('.dt-condition-group');
                self.removeGroup($group);
            });

            // Condition type change
            $(document).on('change', '.dt-condition-type', function() {
                var $row = $(this).closest('.dt-condition-row');
                self.updateOperators($row);
                self.updateValueField($row);
            });

            // Operator change
            $(document).on('change', '.dt-condition-operator', function() {
                var $row = $(this).closest('.dt-condition-row');
                self.updateValueField($row);
            });
            
            // Form submit validation
            $('form').on('submit', function(e) {
                if (!self.validateConditions()) {
                    e.preventDefault();
                    return false;
                }
            });
        },

        /**
         * Add a new condition to a group
         */
        addCondition: function($group) {
            var $list = $group.find('.dt-conditions-list');
            var groupId = $group.data('group-id');
            var index = $list.find('.dt-condition-row').length;
            
            // Get template
            var template = $('#dt-condition-row-template').html();
            template = template.replace(/\{\{INDEX\}\}/g, index);
            template = template.replace(/\{\{GROUP_ID\}\}/g, groupId);
            
            // Add to list
            $list.append(template);
            
            // Initialize the new row
            var $newRow = $list.find('.dt-condition-row').last();
            this.updateOperators($newRow);
        },

        /**
         * Remove a condition
         */
        removeCondition: function($row) {
            var $group = $row.closest('.dt-condition-group');
            var $list = $group.find('.dt-conditions-list');
            
            // Allow removing if there's more than one condition row
            // Or if it's the only row but it's empty (no type selected)
            var rowCount = $list.find('.dt-condition-row').length;
            var isEmptyRow = !$row.find('.dt-condition-type').val();
            
            if (rowCount <= 1 && !isEmptyRow) {
                alert(dtConditionBuilder.i18n.minOneCondition);
                return;
            }
            
            $row.fadeOut(200, function() {
                $(this).remove();
            });
        },

        /**
         * Add a new condition group
         */
        addGroup: function() {
            var $container = $('#dt-condition-groups');
            var groupCount = $container.find('.dt-condition-group').length;
            var newGroupId = groupCount;
            
            // Hide OR separator on last group before adding new one
            $container.find('.dt-condition-group:last .dt-or-separator').show();
            
            // Create new group HTML
            var $newGroup = $('<div>', {
                'class': 'dt-condition-group',
                'data-group-id': newGroupId
            });
            
            // Group header
            var $header = $('<div>', {'class': 'dt-condition-group-header'});
            $header.append(
                $('<span>', {'class': 'dt-group-label'}).text(dtConditionBuilder.i18n.conditionGroup + ' ' + (newGroupId + 1))
            );
            $header.append(
                $('<button>', {
                    'type': 'button',
                    'class': 'button dt-remove-group',
                    'text': dtConditionBuilder.i18n.removeGroup
                })
            );
            $newGroup.append($header);
            
            // Conditions list
            var $list = $('<div>', {'class': 'dt-conditions-list'});
            $newGroup.append($list);
            
            // Group actions
            var $actions = $('<div>', {'class': 'dt-condition-group-actions'});
            var $addBtn = $('<button>', {
                'type': 'button',
                'class': 'button dt-add-condition'
            });
            $addBtn.append($('<span>', {'class': 'dashicons dashicons-plus-alt'}));
            $addBtn.append(' ' + dtConditionBuilder.i18n.addConditionAnd);
            $actions.append($addBtn);
            $newGroup.append($actions);
            
            // OR separator (initially hidden, will be shown if another group is added)
            var $separator = $('<div>', {'class': 'dt-or-separator', 'style': 'display:none;'});
            $separator.append($('<span>').text(dtConditionBuilder.i18n.or));
            $newGroup.append($separator);
            
            // Add to container
            $container.append($newGroup);
            
            // Add first condition
            this.addCondition($newGroup);
            
            // Update group numbers
            this.updateGroupNumbers();
        },

        /**
         * Remove a condition group
         */
        removeGroup: function($group) {
            var $container = $('#dt-condition-groups');
            
            // Don't allow removing the last group
            if ($container.find('.dt-condition-group').length <= 1) {
                alert(dtConditionBuilder.i18n.minOneGroup);
                return;
            }
            
            $group.fadeOut(200, function() {
                $(this).remove();
                ConditionBuilder.updateGroupNumbers();
            });
        },

        /**
         * Update operators based on condition type
         */
        updateOperators: function($row) {
            var $typeSelect = $row.find('.dt-condition-type');
            var $operatorSelect = $row.find('.dt-condition-operator');
            var conditionType = $typeSelect.val();
            
            if (!conditionType) {
                return;
            }
            
            // Get type-specific operators from data attribute
            var typeOperators = $operatorSelect.data('type-operators');
            var allowedOps = typeOperators[conditionType] || [];
            
            // Show/hide options
            $operatorSelect.find('option').each(function() {
                var opValue = $(this).val();
                if (allowedOps.indexOf(opValue) !== -1 || opValue === '') {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
            
            // Only reset operator if current selection is NOT in allowed operators list
            var currentOp = $operatorSelect.val();
            if (currentOp && allowedOps.indexOf(currentOp) === -1) {
                // Current operator is invalid for this condition type, select first valid one
                $operatorSelect.val(allowedOps[0]);
            }
        },

        /**
         * Update all operators
         */
        updateAllOperators: function() {
            var self = this;
            $('.dt-condition-row').each(function() {
                self.updateOperators($(this));
            });
        },

        /**
         * Update value field based on condition type and operator
         */
        updateValueField: function($row) {
            var $typeSelect = $row.find('.dt-condition-type');
            var conditionType = $typeSelect.val();
            var $helpDiv = $row.find('.dt-condition-help');
            var $valueField = $row.find('.dt-condition-value');
            var $emailField = $row.find('.dt-condition-email-field');

            if (!conditionType) {
                this.destroySelect2($row);
                $valueField.attr('type', 'text').show();
                $helpDiv.hide();
                if ($emailField.length) $emailField.hide();
                return;
            }

            // Handle payment_method condition type - static multi-select dropdown
            if (conditionType === 'payment_method') {
                var $valueContainer = $valueField.parent();

                if (!$valueContainer.find('.dt-payment-method-container').length) {
                    var originalName = $valueField.attr('name') || '';
                    var currentVal   = $valueField.val();
                    var selectedIds  = [];
                    try { selectedIds = JSON.parse(currentVal) || []; } catch(e) {
                        if (currentVal) selectedIds = [currentVal];
                    }

                    $valueField.remove();

                    var paymentMethods = (typeof dtConditionBuilder !== 'undefined' && dtConditionBuilder.paymentMethods)
                        ? dtConditionBuilder.paymentMethods : [];

                    var optionsHTML = '';
                    paymentMethods.forEach(function(m) {
                        var sel = (selectedIds.indexOf(m.id) !== -1) ? ' selected' : '';
                        optionsHTML += '<option value="' + $('<div>').text(m.id).html() + '"' + sel + '>'
                            + $('<div>').text(m.title).html() + '</option>';
                    });

                    var containerHTML =
                        '<div class="dt-payment-method-container" style="width:100%;">' +
                            '<select class="dt-payment-method-select" multiple style="width:100%;">' +
                                optionsHTML +
                            '</select>' +
                            '<input type="hidden" class="dt-condition-value"' +
                                (originalName ? ' name="' + originalName + '"' : '') +
                                ' value=\'' + JSON.stringify(selectedIds) + '\'>' +
                        '</div>';

                    $valueContainer.append(containerHTML);

                    // Sync select → hidden on change
                    var $newSelect = $valueContainer.find('.dt-payment-method-select');
                    var $hidden    = $valueContainer.find('.dt-condition-value');

                    $newSelect.on('change.dtPayment', function() {
                        $hidden.val(JSON.stringify($(this).val() || []));
                    });

                    // Apply Select2 for nicer multi-select UI
                    if ($.fn.select2) {
                        $newSelect.select2({
                            placeholder: (dtConditionBuilder.i18n && dtConditionBuilder.i18n.selectPaymentMethod)
                                ? dtConditionBuilder.i18n.selectPaymentMethod : '選擇付款方式...',
                            allowClear: true,
                            dropdownParent: $valueContainer
                        });
                    }
                }
                $helpDiv.hide();
                return;
            }

            // Handle coupon_activation condition type - insert coupon mapping UI
            if (conditionType === 'coupon_activation') {
                // Remove existing value field and replace with coupon mapping interface
                var $valueContainer = $valueField.parent();
                
                // Check if coupon mapping UI already exists
                if (!$valueContainer.find('.dt-coupon-mappings-container').length) {
                    // Save the name attribute from the original field before removing it
                    var originalName = $valueField.attr('name') || '';
                    
                    // Remove old value input
                    $valueField.remove();
                    
                    // Create coupon mapping HTML, preserving the name attribute on the hidden field
                    var mappingHTML = 
                        '<div class="dt-coupon-mappings-container">' +
                            '<div class="dt-coupon-mappings-list">' +
                                '<div class="dt-coupon-mapping-row">' +
                                    '<input type="text" class="dt-mapping-coupon" placeholder="Coupon Code" style="width: 45%; margin-right: 2%;">' +
                                    '<input type="text" class="dt-mapping-email" placeholder="Email (optional)" style="width: 45%; margin-right: 2%;" list="dt-email-suggestions-0">' +
                                    '<button type="button" class="dt-remove-mapping" style="width: 8%;">Remove</button>' +
                                    '<datalist id="dt-email-suggestions-0"></datalist>' +
                                '</div>' +
                            '</div>' +
                            '<button type="button" class="dt-add-mapping" style="margin-top: 10px;">Add Coupon</button>' +
                            '<input type="hidden" class="dt-condition-value"' + (originalName ? ' name="' + originalName + '"' : '') + ' value=\'{"mappings":[{"coupon":"","email":""}]}\'>' +
                        '</div>';
                    
                    // Insert new HTML
                    $valueContainer.append(mappingHTML);
                    
                    // Initialize the hidden value for the first empty row after DOM update
                    setTimeout(function() {
                        var $hiddenValue = $row.find('.dt-condition-value');
                        var structuredValue = {
                            mappings: [{
                                coupon: '',
                                email: ''
                            }]
                        };
                        $hiddenValue.val(JSON.stringify(structuredValue));
                    }, 0);
                }
                
                // Hide help div for coupon activation
                $helpDiv.hide();
                return; // Exit early, don't run Select2 logic
            }

            // Show contextual help where relevant.
            var helpText = '';
            switch (conditionType) {
                case 'product':
                    helpText = dtConditionBuilder.i18n.productIdsHelp;
                    break;
                case 'product_category':
                    helpText = dtConditionBuilder.i18n.categoryIdsHelp;
                    break;
                case 'cart_total':
                    helpText = dtConditionBuilder.i18n.cartTotalHelp;
                    break;
                case 'cart_quantity':
                    helpText = dtConditionBuilder.i18n.cartQuantityHelp;
                    break;
                case 'user_role':
                    helpText = dtConditionBuilder.i18n.userRoleHelp;
                    break;
                case 'user_logged_in':
                    helpText = dtConditionBuilder.i18n.userLoggedInHelp;
                    break;
            }

            if (helpText) {
                $helpDiv.find('.dt-help-text').text(helpText);
                $helpDiv.show();
            } else {
                $helpDiv.hide();
            }

            if (this.shouldUseSelect2(conditionType)) {
                this.initializeSelect2($row, conditionType);
            } else {
                this.destroySelect2($row);
                
                // If value field was removed (e.g., when switching FROM coupon_activation / payment_method), recreate it
                if (!$row.find('.dt-condition-value').length) {
                    // Remove coupon mapping container if exists
                    $row.find('.dt-coupon-mappings-container').remove();
                    // Remove payment method container if exists
                    $row.find('.dt-payment-method-container').remove();
                    
                    // Find value container (should be the cell containing value input)
                    var $valueContainer = $row.find('.dt-condition-operator').parent().find('td:last');
                    if (!$valueContainer.length) {
                        $valueContainer = $row.find('td:last');
                    }
                    
                    // Add new text input
                    $valueContainer.append('<input type="text" class="dt-condition-value" style="width: 100%;">');
                } else {
                    $valueField.attr('type', 'text').show();
                }
            }
        },

        /**
         * Update group numbers
         */
        updateGroupNumbers: function() {
            $('.dt-condition-group').each(function(index) {
                $(this).attr('data-group-id', index);
                $(this).find('.dt-group-label').text(dtConditionBuilder.i18n.conditionGroup + ' ' + (index + 1));
                
                // Update hidden group_id inputs
                $(this).find('input[name*="[group_id]"]').val(index);
                
                // Update name attributes
                $(this).find('select, input').not('[name*="[group_id]"]').each(function() {
                    var name = $(this).attr('name');
                    if (name) {
                        // Replace the group index in the name
                        name = name.replace(/conditions\[\d+\]/, 'conditions[' + index + ']');
                        $(this).attr('name', name);
                    }
                });
                
                // Show/hide OR separator
                if (index < $('.dt-condition-group').length - 1) {
                    $(this).find('.dt-or-separator').show();
                } else {
                    $(this).find('.dt-or-separator').hide();
                }
            });
        },
        
        /**
         * Validate conditions before form submit
         */
        validateConditions: function() {
            var hasValidCondition = false;
            var hasEmptyCondition = false;
            
            $('.dt-condition-group').each(function() {
                $(this).find('.dt-condition-row').each(function() {
                    var $type = $(this).find('.dt-condition-type');
                    var $operator = $(this).find('.dt-condition-operator');
                    var $value = $(this).find('.dt-condition-value');
                    
                    var type = $type.val();
                    var operator = $operator.val();
                    var value = $value.val();
                    
                    // Check if this is a complete condition
                    if (type && operator && value) {
                        hasValidCondition = true;
                    }
                    // Check if this is a partially filled condition
                    else if (type && !value) {
                        hasEmptyCondition = true;
                    }
                });
            });
            
            // If there's a partially filled condition, show error
            if (hasEmptyCondition && !hasValidCondition) {
                alert(dtConditionBuilder.i18n.completeConditions || 'Please complete all condition fields or remove empty conditions.');
                return false;
            }
            
            // Allow saving if there are no conditions (will be handled by backend)
            return true;
        },
        
        /**
         * Check if condition type should use Select2
         */
        shouldUseSelect2: function(conditionType) {
            return ['product', 'product_category', 'product_tag', 'brand'].indexOf(conditionType) !== -1;
        },
        
        /**
         * Get AJAX action name for condition type
         */
        getAjaxAction: function(conditionType) {
            var actions = {
                'product': 'dt_search_products',
                'product_category': 'dt_search_categories',
                'product_tag': 'dt_search_categories',
                'brand': 'dt_search_brands'
            };
            return actions[conditionType] || '';
        },
        
        /**
         * Get placeholder text for condition type
         */
        getPlaceholder: function(conditionType) {
            var placeholders = {
                'product': dtConditionBuilder.i18n.searchProducts || 'Search products...',
                'product_category': dtConditionBuilder.i18n.searchCategories || 'Search categories...',
                'product_tag': dtConditionBuilder.i18n.searchCategories || 'Search tags...',
                'brand': dtConditionBuilder.i18n.searchBrands || 'Search brands...'
            };
            return placeholders[conditionType] || 'Search...';
        },

        /**
         * Determine taxonomy used for a given condition type, if any.
         */
        getTaxonomy: function(conditionType) {
            if (conditionType === 'product_category') {
                return 'product_cat';
            }
            if (conditionType === 'product_tag') {
                return 'product_tag';
            }
            return null;
        },
        
        /**
         * Get Select2 configuration for condition type
         */
        getSelect2Config: function(conditionType) {
            var self = this;
            var taxonomy = self.getTaxonomy(conditionType);

            return {
                width: '100%',
                multiple: true,
                minimumInputLength: 2,
                placeholder: self.getPlaceholder(conditionType),
                tags: false,
                ajax: {
                    url: dtConditionBuilder.ajaxUrl,
                    dataType: 'json',
                    delay: 300,
                    cache: false,
                    data: function(params) {
                        var payload = {
                            action: self.getAjaxAction(conditionType),
                            term: params.term,
                            security: dtConditionBuilder.nonce
                        };
                        if (taxonomy) {
                            payload.taxonomy = taxonomy;
                        }
                        return payload;
                    },
                    processResults: function(response) {
                        if (response && response.success) {
                            if (response.data && response.data.data) {
                                return { results: response.data.data };
                            }
                            if (response.data) {
                                return { results: response.data };
                            }
                        }
                        return { results: [] };
                    }
                },
                language: {
                    inputTooShort: function() {
                        return dtConditionBuilder.i18n.minChars || 'Please enter 2 or more characters';
                    },
                    searching: function() {
                        return dtConditionBuilder.i18n.searching || 'Searching...';
                    },
                    noResults: function() {
                        return dtConditionBuilder.i18n.noResults || 'No results found';
                    }
                }
            };
        },
        
        /**
         * Initialize Select2 on a value field
         */
        initializeSelect2: function($row, conditionType) {
            var self = this;
            var $valueField = $row.find('.dt-condition-value');
            var $select = this.ensureSelectElement($row);

            // Always hide the original input while Select2 is active.
            $valueField.attr('type', 'hidden').hide();

            // Destroy existing Select2 instance to avoid duplicate bindings.
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.off('.dtCondition');
                $select.select2('destroy');
            }

            // Parse any saved value from the hidden field.
            var storedIds = this.parseStoredValue($valueField.val());

            // Reset select options and, if we have stored IDs, pre-populate.
            $select.empty();

            if (storedIds.length > 0) {
                this.fetchInitialOptions(conditionType, storedIds).done(function(items) {
                    var hydrated = Array.isArray(items) && items.length ? items : storedIds.map(function(id) {
                        return { id: id, text: id };
                    });

                    hydrated.forEach(function(item) {
                        var option = new Option(item.text, item.id, true, true);
                        $select.append(option);
                    });

                    self.applySelect2($select, conditionType, $valueField);
                }).fail(function() {
                    storedIds.forEach(function(id) {
                        var fallback = new Option(id, id, true, true);
                        $select.append(fallback);
                    });
                    self.applySelect2($select, conditionType, $valueField);
                });
            } else {
                self.applySelect2($select, conditionType, $valueField);
            }
        },
        
        /**
         * Initialize Select2 on existing condition rows
         */
        initializeExistingSelect2: function() {
            var self = this;
            $('.dt-condition-row').each(function() {
                var $row = $(this);
                var conditionType = $row.find('.dt-condition-type').val();
                
                if (conditionType && self.shouldUseSelect2(conditionType)) {
                    self.initializeSelect2($row, conditionType);
                }

                // Initialize Select2 for PHP-rendered payment_method selects
                if (conditionType === 'payment_method') {
                    var $container = $row.find('.dt-payment-method-container');
                    if ($container.length) {
                        var $select = $container.find('.dt-payment-method-select');
                        var $hidden = $container.find('.dt-condition-value');

                        // Bind sync event (only once)
                        $select.off('change.dtPayment').on('change.dtPayment', function() {
                            $hidden.val(JSON.stringify($(this).val() || []));
                        });

                        // Apply Select2
                        if ($.fn.select2 && !$select.hasClass('select2-hidden-accessible')) {
                            $select.select2({
                                placeholder: (dtConditionBuilder.i18n && dtConditionBuilder.i18n.selectPaymentMethod)
                                    ? dtConditionBuilder.i18n.selectPaymentMethod : '選擇付款方式...',
                                allowClear: true,
                                dropdownParent: $container
                            });
                        }
                    }
                }
            });
        },

        /**
         * Ensure there is a select element available for Select2.
         */
        ensureSelectElement: function($row) {
            var $valueField = $row.find('.dt-condition-value');
            var $select = $row.find('select.dt-condition-value-select');

            if (!$select.length) {
                $select = $('<select>', {
                    'class': 'dt-condition-value-select',
                    'multiple': 'multiple'
                });
                $select.insertAfter($valueField);
            }

            $select.css('width', '100%');

            return $select;
        },

        /**
         * Convert stored string value into an array of IDs.
         */
        parseStoredValue: function(value) {
            if (!value) {
                return [];
            }

            var parsed = [];

            if (Array.isArray(value)) {
                parsed = value;
            } else {
                try {
                    var decoded = JSON.parse(value);
                    if (Array.isArray(decoded)) {
                        parsed = decoded;
                    }
                } catch (err) {
                    parsed = value.split(',').map(function(id) {
                        return id.trim();
                    }).filter(function(id) {
                        return id !== '';
                    });
                }
            }

            return parsed.map(function(id) {
                return id && typeof id === 'object' && id.id ? id.id : id;
            });
        },

        /**
         * Apply Select2 to the provided select element and synchronize selections.
         */
        applySelect2: function($select, conditionType, $valueField) {
            var self = this;
            var config = this.getSelect2Config(conditionType);
            var $parent = $valueField.closest('.dt-condition-value-field');

            if ($parent.length) {
                config.dropdownParent = $parent;
            }

            $select.select2(config);

            var syncValue = function() {
                var selectedIds = $select.val() || [];
                if (selectedIds.length) {
                    $valueField.val(JSON.stringify(selectedIds));
                } else {
                    $valueField.val('');
                }
            };

            $select.off('.dtCondition');
            $select.on('change.dtCondition select2:clear.dtCondition', syncValue);

            // Ensure hidden field reflects current selections after initialization.
            syncValue();
        },

        /**
         * Fetch initial option objects so Select2 can display readable labels.
         */
        fetchInitialOptions: function(conditionType, ids) {
            var action = this.getAjaxAction(conditionType);
            var taxonomy = this.getTaxonomy(conditionType);
            if (!action || !ids.length) {
                return $.Deferred().resolve([]).promise();
            }

            return $.ajax({
                url: dtConditionBuilder.ajaxUrl,
                dataType: 'json',
                method: 'GET',
                data: (function() {
                    var payload = {
                        action: action,
                        security: dtConditionBuilder.nonce,
                        selected: ids.join(',')
                    };
                    if (taxonomy) {
                        payload.taxonomy = taxonomy;
                    }
                    return payload;
                })()
            }).then(function(response) {
                if (response && response.success) {
                    if (response.data && response.data.data) {
                        return response.data.data;
                    }
                    if (response.data) {
                        return response.data;
                    }
                }
                return [];
            });
        },

        /**
         * Destroy Select2 instance and restore the original value field when not needed.
         */
        destroySelect2: function($row) {
            var $select = $row.find('select.dt-condition-value-select');
            var $valueField = $row.find('.dt-condition-value');

            if ($select.length) {
                if ($select.hasClass('select2-hidden-accessible')) {
                    $select.off('.dtCondition');
                    $select.select2('destroy');
                }
                $select.remove();
            }

            $valueField.show().attr('type', 'text');
        }
    };

    /**
     * Handle add mapping button
     */
    $(document).on('click', '.dt-add-mapping', function() {
        var $button = $(this);
        var $container = $button.closest('.dt-coupon-mappings-container');
        var $list = $container.find('.dt-coupon-mappings-list');
        var $row = $button.closest('.dt-condition-row');
        var groupId = $row.find('.dt-condition-type').data('group');
        var condIndex = $row.find('.dt-condition-type').data('index');
        
        var mapIndex = $list.children('.dt-coupon-mapping-row').length;
        
        var newRow = '<div class="dt-coupon-mapping-row" data-map-index="' + mapIndex + '">' +
            '<input type="text" class="dt-mapping-coupon" value="" placeholder="Coupon Code" style="width: 45%;">' +
            '<input type="text" class="dt-mapping-email" value="" placeholder="Email (optional)" ' +
            'list="dt-email-suggestions-' + groupId + '-' + condIndex + '-' + mapIndex + '" style="width: 45%;">' +
            '<button type="button" class="button dt-remove-mapping" style="width: 8%;" title="Remove">' +
            '<span class="dashicons dashicons-no-alt"></span></button>' +
            '<datalist id="dt-email-suggestions-' + groupId + '-' + condIndex + '-' + mapIndex + '"></datalist>' +
            '</div>';
        
        $list.append(newRow);
        updateCouponMappingValue($row);
    });
    
    /**
     * Handle remove mapping button
     */
    $(document).on('click', '.dt-remove-mapping', function() {
        var $button = $(this);
        var $mappingRow = $button.closest('.dt-coupon-mapping-row');
        var $row = $button.closest('.dt-condition-row');
        
        $mappingRow.remove();
        updateCouponMappingValue($row);
    });
    
    /**
     * Handle coupon/email field changes in mapping rows
     */
    $(document).on('input', '.dt-mapping-coupon, .dt-mapping-email', function() {
        var $this = $(this);
        var $row = $this.closest('.dt-condition-row');
        
        updateCouponMappingValue($row);
        
        // If email field, fetch suggestions
        if ($this.hasClass('dt-mapping-email')) {
            var inputValue = $this.val().trim();
            
            // Fetch suggestions if at least 1 character
            if (inputValue.length >= 1) {
                clearTimeout(emailSuggestionTimeout);
                emailSuggestionTimeout = setTimeout(function() {
                    fetchEmailSuggestions($this, inputValue);
                }, 300);
            }
        }
    });
    
    /**
     * Update hidden value with all coupon-email mappings
     */
    function updateCouponMappingValue($row) {
        var $hiddenValue = $row.find('.dt-condition-value');
        var $mappingRows = $row.find('.dt-coupon-mapping-row');
        
        var mappings = [];
        $mappingRows.each(function() {
            var $mappingRow = $(this);
            var coupon = $mappingRow.find('.dt-mapping-coupon').val().trim().toUpperCase();
            var email = $mappingRow.find('.dt-mapping-email').val().trim();
            
            if (coupon) {
                mappings.push({
                    coupon: coupon,
                    email: email
                });
            }
        });
        
        var structuredValue = {
            mappings: mappings
        };
        
        $hiddenValue.val(JSON.stringify(structuredValue));
    }
    
    /**
     * Email suggestion timeout
     */
    var emailSuggestionTimeout;
    
    /**
     * Fetch email suggestions from server
     */
    function fetchEmailSuggestions($field, query) {
        var $row = $field.closest('.dt-condition-row');
        var datalistId = $field.attr('list');
        var $datalist = $('#' + datalistId);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'dt_search_customer_emails',
                query: query,
                nonce: dtConditionBuilder.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    $datalist.empty();
                    $.each(response.data, function(index, email) {
                        $datalist.append($('<option>').val(email));
                    });
                }
            }
        });
    }

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        if ($('.dt-tab-conditions').length) {
            ConditionBuilder.init();
        }
    });

})(jQuery);
