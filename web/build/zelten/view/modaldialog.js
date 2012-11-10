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
        cancelAction: function(e) {
            this.$el.modal('hide');
            this.remove();

            return false;
        },
        successAction: function(e) {
            this.success(e);
            this.cancelAction();

            return false;
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
