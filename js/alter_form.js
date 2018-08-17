/*!
 * This file is a part of Mibew Bulk Logs Operations Plugin
 *
 * Copyright 2018 the original author or authors.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

$(document).ready(function(){
    $('#content #search-button').append('<input id="search-export-button" type="button" class="submit-button-background login-button" value="' + Mibew.Localization.trans('Download') + '"> <input id="search-delete-button" type="button" class="submit-button-background login-button" value="' + Mibew.Localization.trans('Delete') + '">');
    var form = $('#content #search-button').closest('form');
    $('#search-export-button').click(function(e){
        var new_form = form.clone();
        new_form.css('display', 'none')
                .appendTo($('#content'))
                .attr('action',  form.attr('action') + '/bulk_export')
                .submit();
        new_form.remove();
    });
    $('#search-delete-button').click(function(e){
        Mibew.Utils.confirm(Mibew.Localization.trans('This action is irreversible, proceed anyway?'), function(value) {
            if (value) {
                form.attr('action', form.attr('action') + '/bulk_delete').submit();
            }
        });
    });
});
