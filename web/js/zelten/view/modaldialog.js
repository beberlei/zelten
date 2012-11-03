/**
 * Generic view that wraps any element into a Bootstrap Modal
 */
define(["backbone"], function(Backbone) {
    var ModalConfirmDialogView = Backbone.View.extend({
        events: {
            "click .cancel": "cancelAction",
            "click .action-success": "successAction"
        },
        initialize: function(args) {
            this.success = args.success;
            this.params = args.params;
            this.template = _.template($("#modal-confirm-dialog").html() || '');
        },
        cancelAction: function() {
            this.$el.modal('hide');
            this.remove();
        },
        successAction: function() {
            this.success();
            this.cancelAction();
        },
        render: function() {
            var dialog = $(this.template(this.params));
            this.setElement(dialog);
            dialog.modal('show');
            $("body").append(dialog);
        }
    });

    return ModalConfirmDialogView;
});
