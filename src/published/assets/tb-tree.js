'use strict';

var Tree =
    {
        admin_prefix: '',
        parent_id: 1,
        node: 1,

        sortTable : function()
        {
            jQuery('.ui-sortable').sortable({
                scroll: true,
                axis: "y",
                handle: ".tb-sort-me-gently",
                update: function ( event, ui ) {
                    var $currentItem =  ui.item;

                    var node = Core.urlParam('node');
                    if (!$.isNumeric(node)) {
                        node = 1;
                    }
                    jQuery.ajax({
                        url: window.location.href,
                        type: 'POST',
                        dataType: 'json',
                        cache: false,
                        data: {
                            id:               $currentItem.attr('data-id'),
                            parent_id:        node,
                            left_sibling_id:  $currentItem.prev().attr('data-id'),
                            right_sibling_id: $currentItem.next().attr('data-id'),
                            query_type:       'do_change_position'
                        },
                        success: function(response) {

                            if (response.status == true) {
                                TableBuilder.showSuccessNotification(phrase['Порядок следования изменен']);
                            }
                        },
                        error: function (xhr, ajaxOptions, thrownError) {
                            var errorResult = jQuery.parseJSON(xhr.responseText);

                            TableBuilder.showErrorNotification(errorResult.message);
                            TableBuilder.hidePreloader();
                        }
                    });
                }
            });
        },

        init: function()
        {
            Tree.initModalCallbacks();
            Tree.sortTable();

            var idPage = Core.urlParam('id_tree');
            if ($.isNumeric(idPage)) {
                Tree.showEditForm(idPage);
            }

            $('#tb-tree').on('after_open.jstree', function (e, data) {

            }).jstree({
                open_parents: true,
                "core" : {
                    expand_selected_onload: true,
                    dblclick_toggle: true,
                    check_callback: function(operation, node, node_parent, node_position, more) {
                        // operation can be 'create_node', 'rename_node', 'delete_node', 'move_node' or 'copy_node'
                        // in case of 'rename_node' node_position is filled with the new node name

                        if (operation === "move_node") {
                            //console.log(node_parent);
                            return node_parent.id !== "#"; //only allow dropping inside nodes of type 'Parent'
                        }
                        return true;  //allow all other operations
                    }
                },
                "dnd": {
                    check_while_dragging: true
                },
                "contextmenu": {
                    "items": function ($node) {
                        return {
                            "Open": {
                                "label": "Открыть",
                                "action": function (obj) {
                                    window.location.href = window.location.origin + window.location.pathname + '?node='+ $(obj.reference[0].parentElement).data('id');
                                },
                                "separator_after" : true
                            },
                            "Create": {
                                "label": "Добавить",
                                "action": function (obj) {
                                    console.log($(obj.reference[0].parentElement).data('id'));
                                    Tree.showCreateForm($(obj.reference[0].parentElement).data('id'));
                                }
                            },
                            "Rename": {
                                "label": "Редактировать",
                                "action": function (obj) {
                                    Tree.showEditForm($(obj.reference[0].parentElement).data('id'));
                                }
                            },
                            "Delete": {
                                "label": "Удалить",
                                "icon" : "https://cdn1.iconfinder.com/data/icons/nuove/32x32/actions/fileclose.png",
                                "action": function (obj) {
                                    var id = $(obj.reference[0].parentElement).data('id');

                                    if (id == 1) {
                                        return;
                                    }

                                    var $btn = $('.node-del-'+ id);
                                    Tree.doDelete(id, $btn);

                                    $(obj.reference[0].parentElement).remove();
                                },

                                "separator_before" : true
                            }
                        };
                    }
                },
                "plugins" : [ "dnd", "search", "contextmenu" ]
            }).bind("move_node.jstree", function(e, data) {
                //console.log(data);
                //console.log($('#'+data.node.id).prev());

                var $current = jQuery('#'+data.node.id);
                jQuery.ajax({
                    url: window.location.href,
                    type: 'POST',
                    dataType: 'json',
                    cache: false,
                    data: {
                        id:               $current.data('id'),
                        parent_id:        jQuery('#'+data.parent).data('id'),
                        left_sibling_id:  $current.prev().data('id'),
                        right_sibling_id: $current.next().data('id'),
                        query_type:       'do_change_position'
                    },
                    success: function(response) {
                        if (response.status) {
                            $current.data('parent-id', response.parent_id);
                            Tree.setdbl();
                        }
                    }
                });
            }).bind("select_node.jstree", function (e, data) {
                if (typeof data.rslt !== 'undefined') {
                    data.rslt.obj.parents('.jstree-closed').each(function() {
                        data.inst.open_node(this);
                    });
                }

            }).bind("dblclick.jstree", function (event) {
                var node = $(event.target).closest("li");
                window.location.href = window.location.origin + window.location.pathname + '?node='+ node.context.parentElement.dataset.id;
            });;


        }, // end saveMenuPreference

        activeToggle: function(id, isActive)
        {
            isActive = isActive ? 1 : 0;
            jQuery.ajax({
                url: window.location.href,
                type: 'POST',
                dataType: 'json',
                cache: false,
                data: {
                    id: id,
                    is_active: isActive,
                    query_type: 'do_change_active_status'
                },
                success: function(response) {
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    var errorResult = jQuery.parseJSON(xhr.responseText);

                    TableBuilder.showErrorNotification(errorResult.message);
                    TableBuilder.hidePreloader();
                }
            });
        }, // end activeToggle

        activeSetToggle: function(context, id)
        {
            var $table = $(context).closest('table');
            var $smoke = $table.parent().find('.node-active-smoke-lol');
            $smoke.show();

            var data = $table.find(':input').serializeArray();
            data.push({ name: 'id', value: id });
            data.push({ name: 'query_type', value: 'do_change_active_status' });

            console.table(data);

            jQuery.ajax({
                url: window.location.href,
                type: 'POST',
                dataType: 'json',
                cache: false,
                data: data,
                success: function(response) {
                    $smoke.hide();
                }
            });
        }, // end activeSetToggle

        showCreateForm: function(id)
        {
            $('#cf-node', '#tree-create-modal').val(id);
            $('#tree-create-modal').modal('show');
        }, // end showCreateForm

        initModalCallbacks: function()
        {
            $('#tree-create-modal').on('hidden.bs.modal', function() {
                $('#cf-node').val('');
                $("#tree-create-modal-form")[0].reset();
            });
        }, // end initModalCallbacks

        doCreateNode: function()
        {
            var data = $('#tree-create-modal-form').serializeArray();
            data.push({ name: 'query_type', value: 'do_create_node' });
            $('#tree-create-modal').modal('hide');
            jQuery.ajax({
                url: window.location.href,
                type: 'POST',
                dataType: 'json',
                cache: false,
                data: data,
                success: function(response) {
                    if (response.status) {
                        doAjaxLoadContent(location.href);
                    } else {
                        TableBuilder.showErrorNotification(phrase['Что-то пошло не так, попробуйте позже']);
                    }
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    var errorResult = jQuery.parseJSON(xhr.responseText);

                    TableBuilder.showErrorNotification(errorResult.message);
                    TableBuilder.hidePreloader();
                }
            });
        }, // end doCreateNode

        //clone
        getCloneForm: function(id, node)
        {
            $.post( window.location.href, {"query_type" : "clone_record_tree", "id" : id, "node": node})
                .done(function( data ) {
                    doAjaxLoadContent(location.href);
                }).fail(function(xhr, ajaxOptions, thrownError) {
                var errorResult = jQuery.parseJSON(xhr.responseText);
                TableBuilder.showErrorNotification(errorResult.message);
                TableBuilder.hidePreloader();
            });
        },

        showEditForm: function(id)
        {
            TableBuilder.showPreloader();

            var urlPage = "?id_tree=" + id;

            var node = Core.urlParam('node');
            if ($.isNumeric(node)) {
                urlPage += "&node=" + node;
            }

            window.history.pushState(urlPage, '', urlPage);

            jQuery.ajax({
                url: window.location.href,
                type: 'POST',
                dataType: 'json',
                cache: false,
                data: { id: id, query_type: 'get_edit_modal_form' },
                success: function(response) {
                    if (response.status) {

                        jQuery(TableBuilder.form_wrapper).html(response.html);

                        TableBuilder.initFroalaEditor();
                        jQuery(TableBuilder.form_edit).modal('show').css("top", $(window).scrollTop());;
                        jQuery(TableBuilder.form_edit).find('input[data-mask]').each(function() {
                            var $input = jQuery(this);
                            $input.mask($input.attr('data-mask'));
                        });
                        TableBuilder.handleActionSelect();

                        $( ".modal-dialog" ).draggable({ handle: ".modal-header" });
                    } else {
                        TableBuilder.showErrorNotification(phrase['Что-то пошло не так, попробуйте позже']);
                    }

                    TableBuilder.hidePreloader();
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    var errorResult = jQuery.parseJSON(xhr.responseText);

                    TableBuilder.showErrorNotification(errorResult.message);
                    TableBuilder.hidePreloader();
                }
            });
        }, // end showEditForm

        showCreateDefaultForm: function(id)
        {
            TableBuilder.showPreloader();

            jQuery.ajax({
                url: window.location.href,
                type: 'POST',
                dataType: 'json',
                cache: false,
                data: { id: id , query_type: 'get_create_modal_form' },
                success: function(response) {
                    if (response.status) {

                        jQuery(TableBuilder.form_wrapper).html(response.html);

                        TableBuilder.initFroalaEditor();
                        jQuery(TableBuilder.form).modal('show').css("top", $(window).scrollTop());;
                        jQuery(TableBuilder.form).find('input[data-mask]').each(function() {
                            var $input = jQuery(this);
                            $input.mask($input.attr('data-mask'));
                        });
                        TableBuilder.handleActionSelect();

                        $( ".modal-dialog" ).draggable({ handle: ".modal-header" });
                    } else {
                        TableBuilder.showErrorNotification(phrase['Что-то пошло не так, попробуйте позже']);
                    }

                    TableBuilder.hidePreloader();
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    var errorResult = jQuery.parseJSON(xhr.responseText);

                    TableBuilder.showErrorNotification(errorResult.message);
                    TableBuilder.hidePreloader();
                }
            });
        }, // end showEditForm

        doEdit: function(id, table)
        {
            var form = '#edit_form_' + table;

            TableBuilder.edit_form = form;
            TableBuilder.action_url = $(form).attr('action');

            TableBuilder.showPreloader();
            TableBuilder.showFormPreloader(TableBuilder.form_edit);

            $(TableBuilder.edit_form + " .fr-link-insert-layer input").each(function( index ) {
                $( this ).removeAttr("name")
            });

            var values = jQuery(TableBuilder.edit_form).serializeArray();
            values.push({ name: 'id', value: id });
            values.push({ name: 'query_type', value: "do_edit_node" });

            if ($(".file_multi").size() != 0) {
                TableBuilder.buildFiles();
                for (var key in TableBuilder.files) {
                    var filesRes = TableBuilder.files[key];
                    var json = JSON.stringify(filesRes);
                    values.push({ name: key, value: json });
                }
            }

            /* Because serializeArray() ignores unset checkboxes and radio buttons: */
            values = values.concat(
                jQuery(TableBuilder.edit_form).find('input[type=checkbox]:not(:checked)')
                    .map(function() {
                        return { "name": this.name, "value": 0 };
                    }).get()
            );
            var selectMultiple = [];
            jQuery(TableBuilder.edit_form).find('select[multiple="multiple"]').each(function(i, value) {
                if (!$(this).val()) {
                    selectMultiple.push({"name": this.name, "value": ''});
                }
            });

            values = values.concat(selectMultiple);

            jQuery.ajax({
                type: "POST",
                url: window.location.pathname,
                data: values,
                dataType: 'json',
                success: function(response) {

                    TableBuilder.hideFormPreloader(TableBuilder.form_edit);
                    TableBuilder.destroyFroala();

                    if (response.id) {
                        TableBuilder.showSuccessNotification(phrase['Сохранено']);
                        jQuery(TableBuilder.form_edit).modal('hide');
                        doAjaxLoadContent(location.href);
                        $(document).height($(window).height());
                    } else {
                        var errors = '';
                        jQuery(response.errors).each(function(key, val) {
                            errors += val +'<br>';
                        });
                        TableBuilder.showBigErrorNotification(errors);
                    }

                    TableBuilder.hidePreloader();
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    var errorResult = jQuery.parseJSON(xhr.responseText);

                    TableBuilder.showErrorNotification(errorResult.message);
                    TableBuilder.hidePreloader();
                }
            });

        }, // end doEdit

        doDelete: function(id, context)
        {
            jQuery.SmartMessageBox({
                title : phrase["Удалить?"],
                content : phrase["Эту операцию нельзя будет отменить."],
                buttons : '['+phrase['Нет']+']['+phrase['Да']+']'
            }, function(ButtonPressed) {
                if (ButtonPressed === phrase['Да']) {
                    TableBuilder.showPreloader();

                    jQuery.ajax({
                        type: "POST",
                        url: window.location.href,
                        data: { id: id, query_type: 'do_delete_node' },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status) {
                                TableBuilder.showSuccessNotification('Удалено успешно');
                                $("tr[data-id=" + id + "]").remove();
                            } else {
                                TableBuilder.showErrorNotification(phrase['Что-то пошло не так, попробуйте позже']);
                            }

                            TableBuilder.hidePreloader();
                        },
                        error: function (xhr, ajaxOptions, thrownError) {
                            var errorResult = jQuery.parseJSON(xhr.responseText);

                            TableBuilder.showErrorNotification(errorResult.message);
                            TableBuilder.hidePreloader();
                        }
                    });

                }

            });
        }, // end doDelete

    };


//
jQuery(document).ready(function(){
    Tree.init();

    $(document).on('click', '.modal-header button, .modal-footer button', function (e) {
        var url = Core.delPrm("id_tree");

        $(document).height($(window).height());
        window.history.pushState(url, '', url);
    });

});
