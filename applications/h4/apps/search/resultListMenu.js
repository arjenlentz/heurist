/**
* Menu for result list
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2014 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*  
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/


$.widget( "heurist.resultListMenu", {

    // default options
    options: {
        // callbacks
    },
    
    _query_request: null, //keep current query request

    // the widget's constructor
    _create: function() {

        var that = this;

        this.element
        .css('font-size', '0.9em')
        // prevent double click to select text
        .disableSelection();

        this.divMainMenuItems = $('<ul>').addClass('horizontalmenu').appendTo(this.element);
        
        this._initMenu('Search');
        this._initMenu('Selected');
        this._initMenu('Collected');
        this._initMenu('Layout');
        this.divMainMenuItems.menu();

        
        //-----------------------     listener of global events
        var sevents = top.HAPI4.Event.ON_REC_SEARCHSTART; 
        /*top.HAPI4.Event.LOGIN+' '+top.HAPI4.Event.LOGOUT;
        if(this.options.isapplication){
            sevents = sevents + ' ' + top.HAPI4.Event.ON_REC_SEARCHRESULT + ' ' + top.HAPI4.Event.ON_REC_SEARCHSTART + ' ' + top.HAPI4.Event.ON_REC_SELECT;
        }*/

        $(this.document).on(sevents, function(e, data) {

            if(e.type == top.HAPI4.Event.LOGIN || e.type == top.HAPI4.Event.LOGOUT){

                that._refresh();
            }else if(e.type == top.HAPI4.Event.ON_REC_SEARCHRESULT){

            }else if(e.type == top.HAPI4.Event.ON_REC_SEARCHSTART){
                
                that._query_request = data;  //keep current query request 
                
            }else if(e.type == top.HAPI4.Event.ON_REC_SELECT){
                
                
            }
            //that._refresh();
        });        
        
        this._refresh();

    }, //end _create

    // Any time the widget is called with no arguments or with only an option hash, 
    // the widget is initialized; this includes when the widget is created.
    _init: function() {
        
    },
    //Called whenever the option() method is called
    //Overriding this is useful if you can defer processor-intensive changes for multiple option change
    _setOptions: function( ) {
        this._superApply( arguments );
    },

    /* 
    * private function 
    * show/hide buttons depends on current login status
    */
    _refresh: function(){

        if(top.HAPI4.currentUser.ugr_ID>0){
            $(this.element).find('.logged-in-only').css('visibility','visible');
        }else{
            $(this.element).find('.logged-in-only').css('visibility','hidden');
        }
                
    },
    // 
    // custom, widget-specific, cleanup.
    _destroy: function() {
        // remove generated elements
        this.btn_Search.remove();
        this.menu_Search.remove();
        this.btn_Selected.remove();
        this.menu_Selected.remove();
        this.btn_Collected.remove();
        this.menu_Collected.remove();
        this.btn_Layout.remove();
        this.menu_Layout.remove();
        this.divMainMenuItems.remove();
    },
    
    _initMenu: function(name){
        
        var that = this;

        //show hide function
        var _hide = function(ele) {
                $( ele ).delay(700).hide();
            },
            _show = function(ele, parent) {
                $('.menu-or-popup').hide(); //hide other
                var menu = $( ele )
                //.css('width', this.btn_user.width())
                .show()
                .position({my: "left top", at: "left bottom", of: parent });
                //$( document ).one( "click", function() { menu.hide(); });
                return false;
            };
            
        var link = $('<a>',{
            text: name, href:'#'
        });
        
        this['btn_'+name] = $('<li>').append(link)
        .appendTo( this.divMainMenuItems );
            
        
        this['menu_'+name] = $('<ul>')
        .load('apps/search/resultListMenu'+name+'.html?t='+(new Date().getTime()), function(){
            that['menu_'+name].addClass('menu-or-popup')
            .css('position','absolute')
            .appendTo( that.document.find('body') )
            .menu({select: function(event, ui){ that._menuActionHandler(ui.item.attr('id')); }})
        })
        //.position({my: "left top", at: "left bottom", of: this['btn_'+name] })
        .hide();
        
        {select: that._menuActionHandler}
        
        this._on( this['btn_'+name], {
            mouseenter : function(){_show(this['menu_'+name], this['btn_'+name])},
            mouseleave : function(){_hide(this['menu_'+name])}
        });
        this._on( this['menu_'+name], {
            mouseenter : function(){_show(this['menu_'+name], this['btn_'+name])},
            mouseleave : function(){_hide(this['menu_'+name])}
        });        
        
        
    },
    

    _menuActionHandler: function(action){

          var that = this;
        
          //var action = ui.item.attr('id');
          if(action == "menu-search-quick"){
              
                $('#btn_search_assistant').click();
              
          }else if(action == "menu-search-advanced"){
              
                //call H3 search builder
                var q = "", 
                    that = this;
                if(!top.HEURIST4.util.isnull(this._query_request) && !top.HEURIST4.util.isempty(this._query_request.q)){
                    q ="&q=" + encodeURIComponent(this._query_request.q);
                }
                var url = top.HEURIST.basePath+ "search/queryBuilderPopup.php?db=" + top.HAPI4.database + q;
                
                top.HEURIST4.util.showDialog(url, { callback: 
                    function(q){
                        if(!top.HEURIST4.util.isempty(q)) {
                            that._query_request.q = q;
                            //that._query_request.w = 'a';
                            that._query_request.orig = 'rec_list';
                            top.HAPI4.RecordMgr.search(that._query_request, $(that.document));
                        }
                    }});
              
              
          }
        
    }
    
});
