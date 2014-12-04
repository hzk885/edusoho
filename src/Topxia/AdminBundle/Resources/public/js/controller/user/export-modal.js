define(function(require, exports, module) {
    var Validator = require('bootstrap.validator');
    require('common/validator-rules').inject(Validator);
    var Notify = require('common/bootstrap-notify');

    exports.run = function() {

        if($('#user-export-btn').data('count') > 20000){
            $('#user-export-btn').addClass('disabled');
        }

        var $modal = $('#user-export-form').parents('.modal');

        var $userSearchForm = $('#user-search-form');
        var roles = $userSearchForm.find('[name=roles]').val(); 
        var keywordType = $userSearchForm.find('[name=keywordType]').val();
        var keyword = $userSearchForm.find('[name=keyword]').val();
        
        var role=$('input[name="roles"]').attr('value',roles);
        var keywordTypes=$('input[name="keywordType"]').attr('value',keywordType);
        var keywords=$('input[name="keyword"]').attr('value',keyword);

        var choices=$('input[name="choices[]"]');
        var checkedChoices = new Array();

        var validator = new Validator({
            element: '#user-export-form',
            autoSubmit: false,
            onFormValidated: function(error, results, $form) {
                if (error) {
                    return false;
                }

                $('#user-export-btn').button('submiting').addClass('disabled');

                $.post($form.attr('action'), $form.serialize(), function(html) {
                    // for(var i=0;i<choices.length;i++){
                    //   if(choices[i].checked==true){
                    //     alert(choices[i].value);
                    //    checkedChoices[i]= choices[i].value;
                    //    }
                    // }


                    $modal.modal('hide');
                    // Notify.success('新用户添加成功');
                    // window.location.reload();
                });
                // .error(function(){
                //     Notify.danger('新用户添加失败');
                // });

            }
        });

    };

});