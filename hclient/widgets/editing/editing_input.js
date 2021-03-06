/**
* Widget for input controls on edit form
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2019 University of Sydney
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


$.widget( "heurist.editing_input", {

    // default options
    options: {
        varid:null,  //id to create imput id, otherwise it is combination of index and detailtype id
        recID: null,  //record id for current record - required for relation marker and file
        recordset:null, //reference to parent recordset

        //field desription is taken from window.hWin.HEURIST4.rectypes
        rectypes: null,  // reference to window.hWin.HEURIST4.rectypes - defRecStructure
        rectypeID: null, //field description is taken either from rectypes[rectypeID] or from dtFields
        dtID: null,      // field type id (for recDetails) or field name (for other Entities)

        //  it is either from window.hWin.HEURIST4.rectype.typedefs.dtFields - retrieved with help rectypeID and dtID
        // object with some mandatory field names
        dtFields: null,

        values: null,
        readonly: false,
        title: '',  //label (overwrite Display label from record structure)
        showclear_button: true,
        showedit_button: true,
        show_header: true, //show/hide label
        suppress_prompts:false, //supress help, error and required features
        useHtmlSelect: false, //NOTE !!!! native select produce double space for option  Chrome touch screen
        detailtype: null,  //overwrite detail type from db (for example freetext instead of memo)
        
        change: null,  //onchange callback
        is_insert_mode:false,
        is_faceted_search:false //is input used in faceted search
    },

    //newvalues:{},  //keep actual value for resource (recid) and file (ulfID)
    detailType:null,
    configMode:null, //configuration settings, mostly for enum and resource types (from field rst_FieldConfig)
    
    isFileForRecord:false,
    entity_image_already_uploaded: false,

    is_disabled: false,
    
    // the constructor
    _create: function() {

        //field description is taken either from rectypes[rectypeID] or from dtFields
        if(this.options.dtFields==null && this.options.dtID>0 && this.options.rectypeID>0 &&
            this.options.rectypes && this.options.rectypes.typedefs && this.options.rectypes.typedefs[this.options.rectypeID])
        {

            this.options.dtFields = this.options.rectypes.typedefs[this.options.rectypeID].dtFields[this.options.dtID];
        }

        if(this.options.dtFields==null){ //field description is not defined
            return;
        }
        this.detailType = this.options.detailtype ?this.options.detailtype :this.f('dty_Type');
        
        if((!(this.options.rectypeID>0)) && this.options.recordset){
            this.options.rectypeID = this.options.recordset.fld(this.options.recordset.getFirstRecord(), 'rec_RecTypeID');
        }
        

        this.configMode = this.f('rst_FieldConfig');
        if(!window.hWin.HEURIST4.util.isempty(this.configMode)){
            if($.type(this.configMode) === "string"){
                try{
                    this.configMode = $.parseJSON(this.configMode);
                }catch(e){
                    this.configMode = null;
                }
            }
            if(!$.isPlainObject(this.configMode)){
                this.configMode = null;
            }
        }
        //by default
        if((this.detailType=="resource" || this.detailType=='file') 
            && window.hWin.HEURIST4.util.isempty(this.configMode))
        {
            this.configMode= {entity:'records'};
        }

//console.log('create '+this.options.dtID);        
//console.log('vals='+this.options.values);

        this.isFileForRecord = (this.detailType=='file' && this.configMode.entity=='records');
        if(this.isFileForRecord){
            this.configMode = {
                    entity:'recUploadedFiles',
            };
        }

        var that = this;

        var required = "";
        if(this.options.readonly || this.f('rst_Display')=='readonly') {
            required = "readonly";
        }else{
            if(!this.options.suppress_prompts && this.f('rst_Display')!='hidden'){
                required = this.f('rst_RequirementType');
            }
        }
        
        var lblTitle = (window.hWin.HEURIST4.util.isempty(this.options.title)?this.f('rst_DisplayName'):this.options.title);

        //header
        if(true || this.options.show_header){
            this.header = $( "<div>")
            .addClass('header '+required)
            //.css('width','150px')
            .css('vertical-align', 'top') 
            .html('<label>' + lblTitle + '</label>')
            .appendTo( this.element );
            
//(this.detailType=="blocktext" || (this.detailType=='file' && this.configMode.entity!='records')  )?'top':''            
        }
        
        var is_sortable = false;
       
        //repeat button        
        if(this.options.readonly || this.f('rst_Display')=='readonly') {

            //spacer
            $( "<span>")
            .addClass('editint-inout-repeat-button')
            .css({'min-width':'20px', display:'table-cell'})
            .appendTo( this.element );

        }else{

            //saw TODO this really needs to check many exist
            var repeatable = (Number(this.f('rst_MaxValues')) != 1)? true : false;
            
            if(!repeatable || this.options.suppress_repeat){  
                //spacer
                $( "<span>")
                .addClass('editint-inout-repeat-button')
                .css({'min-width':'22px', display:'table-cell'})
                .appendTo( this.element );
                
            }else{ //multiplier button
            
                is_sortable = true; 
            
                this.btn_add = $( "<span>")
                .addClass("smallbutton editint-inout-repeat-button ui-icon-circlesmall-plus")
                .appendTo( this.element )
                //.button({icon:"ui-icon-circlesmall-plus", showLabel:false, label:'Add another ' + lblTitle +' value'})
                .attr('tabindex', '-1')
                .attr('title', 'Add another ' + lblTitle +' value' )                    
                .css({display:'table-cell', 'font-size':'2em', cursor:'pointer','vertical-align':'top',
//outline_suppress does not work - so list all these props here explicitely                
                    outline: 'none','outline-style':'none', 'box-shadow':'none',  'border-color':'transparent'
                });
                
                if(this.detailType=="blocktext"){
                    this.btn_add.css({'margin-top':'3px'});    
                }
                
                //this.btn_add.find('span.ui-icon').css({'font-size':'2em'});
                
                // bind click events
                this._on( this.btn_add, {
                    click: function(){

                        if(this.is_disabled) return;

                        if( !(Number(this.f('rst_MaxValues'))>0)  || this.inputs.length < this.f('rst_MaxValues')){
                            this._addInput('');
                        }
                    }
                });
            }
        }        


        //input cell
        this.input_cell = $( "<div>")
        .addClass('input-cell')
        .appendTo( this.element );
        if(is_sortable){
                this.input_cell.sortable({
                    //containment: "parent",
                    delay: 250,
                    items: '.input-div',
                    stop:function(event, ui){
                        
                        var isparententity = (that.f('rst_CreateChildIfRecPtr')==1);
                        if(isparententity){ //remove parent entity flag to avoid autosave
                            that.fset('rst_CreateChildIfRecPtr', 0);
                        }
                        
                        //reorganize
                        that.isChanged(true);
                        that._onChange();
                        that.btn_cancel_reorder.show();
                        
                        if(isparententity){//restore parent entity flag
                            that.fset('rst_CreateChildIfRecPtr', 1);
                        }
                        
                    }});            
                    
                    
                $('<br>').appendTo( this.header );
                this.btn_cancel_reorder = $("<div title='Cancel reorder'>")
                    .appendTo( this.header ).hide()
                    .css({'padding':'1px', 'margin-top':'4px', 'font-size':'0.7em', width: '40px', float: 'right'})
                    .button({label:'Cancel'});
                this._on( this.btn_cancel_reorder, {
                    click: this._restoreOrder} );
                    
        } 
        
        
        //add hidden error message div
        this.error_message = $( "<div>")
        .hide()
        .addClass('heurist-prompt ui-state-error')
        .css({'height': 'auto',
            'padding': '0.2em',
            'border': 0,
            'margin-bottom': '0.2em'})
        .appendTo( this.input_cell );

        //add prompt
        var help_text = window.hWin.HEURIST4.ui.getRidGarbageHelp(this.f('rst_DisplayHelpText'));
        
        this.input_prompt = $( "<div>")
        .html( help_text && !this.options.suppress_prompts ?help_text:'' )
        .addClass('heurist-helper1').css('padding-bottom','1em');
        // we use applyCompetencyLevel from now
        //if(window.hWin.HAPI4.get_prefs('help_on')!=1){
        //    this.input_prompt.hide();
        //}
        this.input_prompt.appendTo( this.input_cell );


        //values are not defined - assign default value
        var values_to_set;
        
        if( !window.hWin.HEURIST4.util.isArray(this.options.values) ){
            var def_value = this.f('rst_DefaultValue');
            
            var isparententity = (this.f('rst_CreateChildIfRecPtr')==1);

            if( !this.options.is_insert_mode || window.hWin.HEURIST4.util.isempty(def_value) || isparententity){
                // reset default value - default value for new record only
                // do not assign default values in edit mode                
                values_to_set = [''];        
            }else if(window.hWin.HEURIST4.util.isArray(def_value)){
                //exclude duplication
                values_to_set = window.hWin.HEURIST4.util.uniqueArray(def_value);//.unique();
            }else{
                values_to_set = [def_value];
            }
            
            if(values_to_set=='increment_new_values_by_1'){
                
                   //find incremented value on server side
                   window.hWin.HAPI4.RecordMgr.increment(this.options.rtyID, this.options.dtyID, 
                     function(response){
                      if(!window.hWin.HEURIST4.util.isnull(response)){
                            if(response.status == window.hWin.ResponseStatus.OK){
                                
                                that.setValue(response.result);
                                that.options.values = that.getValues();
                                that._refresh();
                                
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                      }
                  });
                  this.setValue(0);
                  this.options.values = [0];
                  return;
            }
            
        }else if(this.detailType=='file' || this.detailType=='geo'){
            values_to_set = this.options.values;
        }else {
            values_to_set = this.options.values; //window.hWin.HEURIST4.util.uniqueArray(this.options.values); //.slice();//.unique();
        }
        
        //recreate input elements and assign given values
        this.setValue(values_to_set);
        this.options.values = this.getValues();
        this._refresh();
    }, //end _create

    /* private function */
    _refresh: function(){
        if(this.f('rst_Display')=='hidden'){
            this.element.hide();    
        }else{
            this.element.show();    
        }
        
        if(this.options.showedit_button){
            this.element.find('.btn_add_term').css({'visibility':'visible','max-width':16});
        }else{
            this.element.find('.btn_add_term').css({'visibility':'hidden','max-width':0});
        }
        
        if(this.options.showclear_button){
            this.element.find('.btn_input_clear').css({'visibility':'visible','max-width':16});
        }else{
            this.element.find('.btn_input_clear').css({'visibility':'hidden','max-width':0});
        }
        
        if(this.options.show_header){
            this.header.css('display','table-cell');//show();
        }else{
            this.header.hide();
        }        
    },
    
    _setOptions: function( ) {
        this._superApply( arguments );
        this._refresh();
    },

    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {
        if(this.btn_add){
            this.btn_add.remove();
        }
        // remove generated elements
        if(this.imagelib_select_dialog){
            this.imagelib_select_dialog.remove();
        }
        if(this.header){
            this.header.remove();
        }
        var that = this;
        if(this.inputs){
            $.each(this.inputs, function(index, input){ 

                    if(that.detailType=='blocktext'){
                        var eid = '#'+input.attr('id')+'_editor';
                        tinymce.remove(eid);
                        $(eid).remove(); //remove editor element
                        
                    }else if(that.detailType=='file'){
                        $(input).fileupload('destroy');
                    }else{
                        if($(input).hSelect('instance')!==undefined) $(input).hSelect('destroy');
                    }
                    input.remove();
            } );
            this.input_cell.remove();
        }
    },

    /**
    * get value for given record type structure field
    *
    * dtFields - either from rectypes.typedefs and index is taken from dtFieldNamesToIndex
    *          - or it is object with following list of properties
    dty_Type,
    rst_DisplayName,  //label
    rst_DisplayHelpText  (over dty_HelpText)           //hint
    rst_DisplayExtendedDescription  (over dty_ExtendedDescription) //rollover

    rst_RequirementType,  //requirement
    rst_MaxValues     //repeatability

    rst_DisplayWidth - width in characters

    rst_PtrFilteredIDs
    rst_FilteredJsonTermIDTree      @todo rename to rst_FieldConfig (over dty_JsonConfig)
    rst_TermIDTreeNonSelectableIDs
    dty_TermIDTreeNonSelectableIDs
    *
    *
    * @param fieldname
    */
    f: function(fieldname){

        var val = this.options['dtFields'][fieldname]; //try get by name
        if(window.hWin.HEURIST4.util.isnull(val) && this.options.rectypes){ //try get by index
            var fi = this.options.rectypes.typedefs.dtFieldNamesToIndex;
            if(fi) val = this.options['dtFields'][fi[fieldname]];
        }
        
        if(window.hWin.HEURIST4.util.isempty(val)){ //some default values
            if(fieldname=='rst_RequirementType') val = 'optional'
            else if(fieldname=='rst_MaxValues') val = 1
                else if(fieldname=='dty_Type') val = 'freetext'
                    else if(fieldname=='rst_DisplayHeight' && this.f('dty_Type')=='blocktext') 
                          val = 8 //height in rows
                       else if(fieldname=='rst_DisplayWidth'
                            && (this.f('dty_Type')=='freetext' || this.f('dty_Type')=='url' || 
                                this.f('dty_Type')=='blocktext' || this.f('dty_Type')=='resource'))   
                                    val = this.f('dty_Type')=='freetext'?20:80;  //default minimum width for input fields in ex
        }
        return val;

        /*}else{
        var rfrs = this.options.rectypes.typedefs[this.options.rectypeID].dtFields[this.options.dtID];
        var fi = this.options.rectypes.typedefs.dtFieldNamesToIndex;
        return rfrs[fi[fieldname]];
        }*/
    },
    
    fset: function(fieldname, value){

        if(this.options['dtFields'][fieldname]){
            this.options['dtFields'][fieldname] = value;
        }
        if(this.options.rectypes){ //try get by index
            var fi = this.options.rectypes.typedefs.dtFieldNamesToIndex;
            if(fi) this.options['dtFields'][fi[fieldname]] = value;
        }
        
    },

    //
    //
    //
    _removeInput: function(input_id){
        if(this.inputs.length>1){
            //find in array
            var that = this;
            $.each(this.inputs, function(idx, item){

                var $input = $(item);
                if($input.attr('id')==input_id){
                    if(that.newvalues[input_id]){
                        delete that.newvalues[input_id];
                    }
                    
                    if(that.detailType=='file'){
                        if($input.fileupload('instance')){
                            $input.fileupload('destroy');
                        }
                        var $parent = $input.parents('.input-div');
                        $input.remove();
                        $parent.remove();
                        
                        that.entity_image_already_uploaded = false;
                    }else{
                        if($input.hSelect('instance')!==undefined) $input.hSelect('destroy');
                        
                        //remove element
                        $input.parents('.input-div').remove();
                    }
                    //remove from array
                    that.inputs.splice(idx,1);
                    that._onChange();                    
                    return;
                }

            });

        }else{  //and clear last one
            this._clearValue(input_id, '');
        }
        
    },
    
    _setAutoWidth: function(){

        //auto width
        if ( this.detailType=='freetext' || this.detailType=='integer' || 
             this.detailType=='float' || this.detailType=='url' || this.detailType=='file'){
            $.each(this.inputs, function(index, input){ 
                var ow = $(input).width();
                if(ow<580){
                    var nw = ($(input).val().length+4)+'ex';
                    $(input).css('width', nw);
                    if($(input).width()<ow) $(input).width(ow) //we can only increase - restore
                    else if($(input).width()>600) $(input).width(600); //max width
                }
            });
        }
        
    },
    
    
    _onChange: function(event){
        
        this.error_message.hide();
        $(this.element).find('.ui-state-error').each(function(idx,item){
           if(!$(item).hasClass('heurist-prompt')){
                $(item).removeClass('ui-state-error');    
           }
           
        });
        
        this._setAutoWidth();
        
        if($.isFunction(this.options.change)){
            this.options.change.call( this );    
        }
    },

    /**
    * add input according field type
    *
    * @param value
    * @param idx - index for repetative values
    */
    _addInput: function(value) {

        if(!this.inputs){//init
            this.inputs = [];
            this.newvalues = {};
        }
        
        var that = this;

        var $input = null;
        //@todo check faceted search!!!!! var inputid = 'input'+(this.options.varid?this.options.varid :idx+'_'+this.options.dtID);
        //repalce to uniqueId() if need
        value = window.hWin.HEURIST4.util.isnull(value)?'':value;


        var $inputdiv = $( "<div>" ).addClass('input-div').insertBefore(this.input_prompt);  //.appendTo( this.input_cell );

        if(this.detailType=='blocktext'){

            var dheight = this.f('rst_DisplayHeight');
            
            $input = $( "<textarea>",{rows:dheight,})
            .uniqueId()
            .val(value)
            .addClass('text ui-widget-content ui-corner-all')
            .css({'overflow-x':'hidden'})
            .keydown(function(e){
                if (e.keyCode == 65 && e.ctrlKey) {
                    e.target.select()
                }    
            })
            .keyup(function(){that._onChange();})
            .change(function(){that._onChange();})
            .appendTo( $inputdiv );
            
            var eid = $input.attr('id')+'_editor';
            
            $editor = $( "<div>")
            .attr("id", eid)
            .addClass('text ui-widget-content ui-corner-all')
            .css({'overflow-x':'hidden','display':'inline-block'})
            .appendTo( $inputdiv ).hide();
            
            
              
            var $btn_edit_switcher = $( '<span>html</span>', {title: 'Show/hide Rich text editor'})
                //.addClass('smallicon ui-icon ui-icon-gear btn_add_term')
                .addClass('smallbutton btn_add_term')
                .css({'line-height': '20px','vertical-align':'top',cursor:'pointer','text-decoration':'underline'})
                .appendTo( $inputdiv );
                            
            function __showEditor(is_manual){
                var eid = '#'+$input.attr('id')+'_editor';                    
                
                $input.hide();
                        $(eid).html($.parseHTML($input.val())).width($input.width()).height($input.height()).show();

                        $btn_edit_switcher.text('text');
                        
                        tinymce.init({
                                //target: $editor, 
                                selector: (eid),
                                inline: false,
                                branding: false,
                                elementpath: false,
                                statusbar: true,
                                resize: 'both',
                                menubar: false,
                                entity_encoding:'raw',
                                 setup:function(ed) {
                                   ed.on('change', function(e) {
                                       var newval = ed.getContent();
                                       var nodes = $.parseHTML(newval);
                                       if(nodes && nodes.length==1 &&  !(nodes[0].childElementCount>0) &&
                                           (nodes[0].nodeName=='#text' || nodes[0].nodeName=='P'))
                                       { 
                                           //remove the only tag
                                           $input.val(nodes[0].textContent);
                                       }else{
                                           $input.val(newval);     
                                       }
                                       
                                       //$input.val( ed.getContent() );
                                       that._onChange();
                                   });
                                 },
                                plugins: [
                                    'advlist autolink lists link image preview textcolor', //anchor charmap print 
                                    'searchreplace visualblocks code fullscreen',
                                    'media table contextmenu paste help'  //insertdatetime  wordcount
                                  ],      
                                  //undo redo | code insert  |  fontselect fontsizeselect |  forecolor backcolor | media image link | alignleft aligncenter alignright alignjustify | fullscreen            
                                toolbar: ['formatselect | bold italic forecolor | align | bullist numlist outdent indent | removeformat | help'],
                                content_css: [
                                    '//fonts.googleapis.com/css?family=Lato:300,300i,400,400i'
                                    //,'//www.tinymce.com/css/codepen.min.css'
                                    ]                    
                          });
  
            }

                
            this._on( $btn_edit_switcher, { click: function(){
                    
                    var eid = '#'+$input.attr('id')+'_editor';                    
                    if($input.is(':visible')){
                        __showEditor(true);
                    }else{
                        $btn_edit_switcher.text('html');
                        $input.show();
                        tinymce.remove(eid);
                        $(eid).hide();
                    }
                }});
                
                //what is visible initially
                var nodes = $.parseHTML(value);
                if(nodes && (nodes.length>1 || nodes[0].nodeName!='#text')){ //if it has html show editor at once
                     setTimeout(__showEditor,500); 
                }
              

        }
        else if(this.detailType=='enum' || this.detailType=='relationtype'){

            var dwidth = this.f('rst_DisplayWidth');
            if(parseFloat(dwidth)>0){
                dwidth = dwidth+'ex';
            }

            $input = $('<select>').uniqueId()
                .addClass('text ui-widget-content ui-corner-all')
                .css('width',(dwidth && dwidth!='0')?dwidth:'0px')
                .val(value)
                .appendTo( $inputdiv );
            
            $input = this._recreateSelector($input, value);
            $input = $($input);
            
            function __onTermChange( event, data ){
                
                if(! $($input).attr('radiogroup')){
                
                    if($input.hSelect("instance")!=undefined){
                        
                        var opt = $input.find('option[value="'+$input.val()+'"]');
                        var parentTerms = opt.attr('parents');
                        if(parentTerms){
                            $input.hSelect("widget").find('.ui-selectmenu-text').text( parentTerms+'.'+opt.text() );    
                        }    
                           
                    }else{
                        //restore plain text value               
                        $input.find('option[term-view]').each(function(idx,opt){
                            $(opt).text($(opt).attr('term-view'));
                        });
                        
                        //assign for selected term value in format: parent.child 
                        var opt = $input.find( "option:selected" );
                        var parentTerms = opt.attr('parents');
                        if(parentTerms){
                             opt.text(parentTerms+'.'+opt.attr('term-orig'));
                        }
                    }
                
                }
                that._onChange();
            }
            
            $input.change( __onTermChange );
            
            var allTerms = this.f('rst_FieldConfig');    
            
            if($.isPlainObject(allTerms)){
                this.options.showclear_button = (allTerms.hideclear!=1);
            }
            
            //allow edit terms only for true defTerms enum and if not DT_RELATION_TYPE
            
            if(window.hWin.HEURIST4.util.isempty(allTerms) 
                && (this.options.dtID!=window.hWin.HAPI4.sysinfo['dbconst']['DT_RELATION_TYPE']))
            {

                allTerms = this.f('rst_FilteredJsonTermIDTree');        

                var isVocabulary = !isNaN(Number(allTerms)); 


                var $btn_termsel = $( '<span>', {title: 'Select Term By Picture'})
                .addClass('smallicon ui-icon ui-icon-image')
                .css({'margin-top': '2px'})
                .appendTo( $inputdiv );

                function __showHideSelByImage(){
                    var hasImage = ($input.find('option[term-img=1]').length>0);
                    if(hasImage) {
                        $input.css({'margin-right': '-44px', 'padding-right': '30px'});
                        $btn_termsel.show();   
                    }else{
                        $input.css({'margin-right': 0, 'padding-right': 0});
                        $btn_termsel.hide();   
                    }
                }
                
                __showHideSelByImage();
                

                this._on( $btn_termsel, { click: function(){

                    if(this.is_disabled) return;
                    
                    var all_term_ids = $.map($input.find('option'), function(e) { return e.value; });
                    
                    var request = {};
                    request['a']          = 'search'; //action
                    request['entity']     = 'defTerms';//this.options.entity.entityName;
                    request['details']    = 'list'; //'id';
                    request['request_id'] = window.hWin.HEURIST4.util.random();
                    request['trm_ID'] = all_term_ids;
                    request['withimages'] = 1;
                    
                    //request['DBGSESSID'] = '423997564615200001;d=1,p=0,c=0';
                    var that = this;   
                                                                 
                    //select term by image
                    window.hWin.HAPI4.EntityMgr.doRequest(request, 
                        function(response){
                            if(response.status == window.hWin.ResponseStatus.OK){
                                var recset = new hRecordSet(response.data);
                                if(recset.length()>0){                                  
                                
                                    window.hWin.HEURIST4.ui.showEntityDialog('DefTerms', 
                                        {select_mode:'images', recordset:recset,
                                        onselect:function(event, data){
                                            if(data && data.selection && data.selection.length>0){
                                                $input.val(data.selection[0]);
                                                if($input.hSelect('instance')!==undefined) $input.hSelect('refresh');
                                                that._onChange();
                                            }
                                        }
                                        });
                                                                    
                                }else{
                                    window.hWin.HEURIST4.msg.showMsgFlash('No terms images defined');
                                }
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                        });

                }});

                    
                var $btn_termedit = $( '<span>', {title: 'Add new term to this list'})
                .addClass('smallicon ui-icon ui-icon-gear btn_add_term')
                .css({'margin-top':'2px',cursor:'pointer'})
                .appendTo( $inputdiv );
                //.button({icons:{primary: 'ui-icon-gear'},text:false});
                
                this._on( $btn_termedit, { click: function(){
                    
                if(this.is_disabled) return;
                    
                if(isVocabulary){

                    var type = (this.detailType!='enum')?'relation':'enum';
                    
                    var url = window.hWin.HAPI4.baseURL 
                        + 'admin/structure/terms/editTermForm.php?db='+window.hWin.HAPI4.database
                        + '&treetype='+type+'&parent='+Number(allTerms);
                    
                    window.hWin.HEURIST4.msg.showDialog(url, {height:320, width:700,
                        title: 'Add Term',
                        noClose: true, //hide close button
                        //class:'ui-heurist-bg-light',
                        onpopupload:function(dosframe){
                            $(dosframe.contentDocument).find('#trmName').focus();
                        },
                        callback: function(context){
                                $input.css('width','auto');
                                $input = that._recreateSelector($input, true);
                                $input.change( __onTermChange );
                                
                                __showHideSelByImage();
                        }
                    } );
                    

                }else{
                    
                    var url = window.hWin.HAPI4.baseURL 
                        + 'admin/structure/terms/selectTerms.html?mode=editrecord&db='
                        + window.hWin.HAPI4.database
                        + '&detailTypeID='+this.options.dtID;

                    window.hWin.HEURIST4.msg.showDialog(url, {height:540, width:750,
                        title: 'Select Term',
                        noClose: true, //hide close button
                        //class:'ui-heurist-bg-light',
                        callback: function(editedTermTree, editedDisabledTerms){
                            if(editedTermTree || editedDisabledTerms) {
                                
                                that.options['dtFields']['rst_FilteredJsonTermIDTree'] = editedTermTree;
                                that.options['dtFields']['rst_TermIDTreeNonSelectableIDs'] = editedDisabledTerms
                                                                
                                $input = that._recreateSelector($input, true);
                                
                                $input.change( __onTermChange );
                                
                                __showHideSelByImage();
                            }
                        }
                    });


                }                
                
                
                
                }} ); //end btn onclick
            
            }//allow edit terms only for true defTerms enum
            
            
        }
        else if(this.detailType=='boolean'){

            $input = $( '<input>',{type:'checkbox', value:'1'} )
            .uniqueId()
            .addClass('text ui-widget-content ui-corner-all')
            .css('vertical-align','-3px')
            .change(function(){that._onChange();})
            .appendTo( $inputdiv );

            if(!(value==false || value=='0' || value=='n')){
                $input.prop('checked','checked');
            }

        }
        else if(this.detailType=='rectype'){  //@todo it seems not used need refer via resource type and entity mgr

            $input = $( "<select>" )
            .uniqueId()
            .addClass('text ui-widget-content ui-corner-all')
            .css('width','auto')
            .val(value)
            .appendTo( $inputdiv );

            window.hWin.HEURIST4.ui.createRectypeSelect($input.get(0),null, this.f('cst_EmptyValue'), true);
            if(value){
                $input.val(value);
            }
            $input.change(function(){that._onChange();})

        }
        else if(this.detailType=="user"){ //special case - only groups of current user

            $input = $( "<select>")
            .uniqueId()
            .addClass('text ui-widget-content ui-corner-all')
            .css('width','auto')
            .val(value)
            .change(function(){that._onChange();})
            .appendTo( $inputdiv );

            window.hWin.HEURIST4.ui.createUserGroupsSelect($input.get(0),null,
                [{key:'',title:window.hWin.HR('select user/group...')},
                    {key:window.hWin.HAPI4.currentUser['ugr_ID'], title:window.hWin.HAPI4.currentUser['ugr_FullName'] }] );
            if(value){
                $input.val(value);
            }

        }
        else if(this.detailType=='relmarker'){ 
            
                this.options.showclear_button = false;
                $inputdiv.css({'display':'inline-block','vertical-align':'middle'});
            
                if(this.inputs.length==0){ //show current relations
                
                    //these are relmarker fields from other rectypes that points to this record
                    var isInwardRelation = (that.f('rst_DisplayOrder')>1000);
                
                
                    function __onRelRemove(){
                        var tot_links = that.element.find('.link-div').length;
                        var rev_links = that.element.find('.reverse-relation').length; 
                        if( tot_links-rev_links==0){ //hide this button if there are links
                            that.element.find('.rel_link').show();
                        }else{
                            that.element.find('.rel_link').hide();
                        }
                        if( rev_links==0){
                            that.element.find('.reverse-relation-header').remove();
                        }
                    }
                
                    var __show_addlink_dialog = function(){
                            if(that.is_disabled) return;
                            
                            
                            function __onCloseAddLink(context){
                                    
                                    if(context && context.count>0){
                                        
                                        var link_info = isInwardRelation?context.source:context.target;
                                        link_info.relation_recID = context.relation_recID;
                                        link_info.trm_ID = context.trm_ID;
                                        link_info.is_inward = isInwardRelation;
                                        
                                        var ele = window.hWin.HEURIST4.ui.createRecordLinkInfo($inputdiv,
                                            link_info, true);
                                        ele.insertBefore(that.element.find('.rel_link'));
                                        that.element.find('.rel_link').hide();//hide this button if there are links
                                        ele.on('remove', __onRelRemove);
                                    }
                            }                            
                            
                            var opts = {
                                height:280, width:750, 
                                title: 'Create relationship between records ( Field: "'+window.hWin.HEURIST4.detailtypes.names[that.options.dtID]+'" )',
                                relmarker_dty_ID: that.options.dtID,
                                onClose: __onCloseAddLink 
                            };
                           
                           if(isInwardRelation){
                              opts['source_AllowedTypes'] = that.f('rst_PtrFilteredIDs');
                              opts['target_ID'] = that.options.recID;
                           }else{
                              opts['source_ID'] = that.options.recID;
                           }     
                        
                            window.hWin.HEURIST4.ui.showRecordActionDialog('recordAddLink', opts);
                            
                           /*
                            var url = window.hWin.HAPI4.baseURL 
                                +'hclient/framecontent/recordAddLink.php?db='+window.hWin.HAPI4.database
                                +'&dty_ID=' + that.options.dtID;
                           
                           if(isInwardRelation){
                              url +=  '&source_RecTypes='+that.f('rst_PtrFilteredIDs')+'&target_ID=' + that.options.recID;
                           }else{
                              url +=  '&source_ID=' + that.options.recID;
                           }     
                            
                            window.hWin.HEURIST4.msg.showDialog(url, {height:280, width:750,
                                title: window.hWin.HR('Add relationship'),
                                class:'ui-heurist-bg-light',
                                callback: __onCloseAddLink );
                            */    
                    };
                    
                    
                
                    var sRels = '';
                    if(that.options.recordset){
                    
                    var relations = that.options.recordset.getRelations();
                  
                    if(relations && (relations.direct || relations.reverse)){
                        
                        var ptrset = that.f('rst_PtrFilteredIDs');
                        if(!$.isArray(ptrset)){
                            if(ptrset) ptrset = ptrset.split(',')
                            else ptrset = [];
                        }
                        
                        var allTerms = this.f('rst_FilteredJsonTermIDTree');        
                        var headerTerms = this.f('rst_TermIDTreeNonSelectableIDs') || this.f('dty_TermIDTreeNonSelectableIDs');
                        //var terms = window.hWin.HEURIST4.ui.getPlainTermsList(this.detailType, allTerms, headerTerms, null);

                        var ph_gif = window.hWin.HAPI4.baseURL + 'hclient/assets/16x16.gif';
                        var headers = relations.headers;
                        
                        
                      if(!isInwardRelation){
                            var direct = relations.direct; //outward
                            
                        //take only those that satisify to allowed terms and pointer constraints
                        for(var k in direct){
                            //direct[k]['dtID']==this.options.dtID && 
                            if(direct[k]['trmID']>0){ //relation   
                            
                                
                                if(window.hWin.HEURIST4.ui.isTermInList(this.detailType, allTerms, headerTerms, direct[k]['trmID']))
                                { //it satisfies to allowed relationship types

                                        //verify that target rectype is satisfy to constraints and trmID allowed
                                        var targetID = direct[k].targetID;
                                        var targetRectypeID = headers[targetID][1];
                                        if( headers[targetID]['used_in_reverse']!=1 &&
                                           (ptrset.length==0 || 
                                            window.hWin.HEURIST4.util.findArrayIndex(targetRectypeID, ptrset)>=0))
                                        {
                                            
                                            var ele = window.hWin.HEURIST4.ui.createRecordLinkInfo($inputdiv, 
                                                {rec_ID: targetID, 
                                                 rec_Title: headers[targetID][0], 
                                                 rec_RecTypeID: headers[targetID][1], 
                                                 relation_recID: direct[k]['relationID'], 
                                                 trm_ID: direct[k]['trmID'],
                                                 dtl_StartDate: direct[k]['dtl_StartDate'], 
                                                 dtl_EndDate: direct[k]['dtl_EndDate'],
                                                 is_inward: false
                                                }, true);
                                            ele.on('remove', __onRelRemove);
                                            
                                            headers[targetID]['used_in_direct'] = 1;
                                        }
                                }
                            }
                        }
                        
                      }//!isInwardRelation

                        
                        //small subheader before reverse entries
                        var isSubHeaderAdded = isInwardRelation;
                        
                        //now scan all indirect /inward relations
                        var reverse = relations.reverse; //outward
                        //take only those that satisify to allowed terms and pointer constraints
                        for(var k in reverse){
                            //direct[k]['dtID']==this.options.dtID && 
                            if(reverse[k]['trmID']>0){ //relation   
                                
                                if(window.hWin.HEURIST4.ui.isTermInList(this.detailType, allTerms, headerTerms, reverse[k]['trmID']))
                                { //it satisfies to allowed relationship types
                                
                                        //verify that target rectype is satisfy to constraints and trmID allowed
                                        var targetID = reverse[k].sourceID;
                                        var targetRectypeID = headers[targetID][1];
                                        
                                        if (headers[targetID]['used_in_direct']!=1 && (ptrset.length==0) ||
                                                (window.hWin.HEURIST4.util.findArrayIndex(targetRectypeID, ptrset)>=0))
                                        {
                                            if(!isSubHeaderAdded){
                                                isSubHeaderAdded = true;
                                                $('<div>Referenced by</div>') //Reverse relationships
                                                        .css('padding-top','4px')
                                                        .addClass('header reverse-relation-header')
                                                        .appendTo($inputdiv);
                                            }
                                            
                                            var invTermID = window.hWin.HEURIST4.ui.getInverseTermById(reverse[k]['trmID']);
                                            
                                            var ele = window.hWin.HEURIST4.ui.createRecordLinkInfo($inputdiv, 
                                                {rec_ID: targetID, 
                                                 rec_Title: headers[targetID][0], 
                                                 rec_RecTypeID: targetRectypeID, 
                                                 relation_recID: reverse[k]['relationID'], 
                                                 trm_ID: invTermID,
                                                 dtl_StartDate: reverse[k]['dtl_StartDate'], 
                                                 dtl_EndDate: reverse[k]['dtl_EndDate'],
                                                 is_inward: true
                                                }, true);
                                            ele.addClass('reverse-relation', 1)
                                                .on('remove', __onRelRemove);
                                            
                                            headers[targetID]['used_in_reverse'] = 1;
                                        }
                                }
                            }
                        }
                        
                        
                    }
                }
                
                    /*
                    $input = $( "<div>")
                        .uniqueId()
                        .html(sRels)
                        //.addClass('ui-widget-content ui-corner-all')
                        .appendTo( $inputdiv );
                   */  
                   $inputdiv
                        .uniqueId();
                   $input = $inputdiv;

                   
                   //define explicit add relationship button
                   var $btn_add_rel_dialog = $( "<button>", {title: "Click to add new relationship"})
                        .addClass("rel_link") //.css({display:'block'})
                        .button({icons:{primary: "ui-icon-circle-plus"},label:'&nbsp;&nbsp;&nbsp;Add Relationship'});
                       
                   var rheader = that.element.find('.reverse-relation-header');     
                   if(rheader.length>0){
                        $btn_add_rel_dialog.insertBefore( rheader );
                   }else{
                        $btn_add_rel_dialog.appendTo( $inputdiv );   
                   }
                        
                
                   $btn_add_rel_dialog.click(function(){__show_addlink_dialog()});
                   
                   __onRelRemove();                   
                   /*if( this.element.find('.link-div').length>0){ //hide this button if there are links
                        $btn_add_rel_dialog.hide();
                   }*/

                
                }else{
                    //this is second call - some links are already defined
                    //show popup dialog at once
                    //IJ ASKS to disbale it __show_addlink_dialog();
                    if(this.element.find('.rel_link').is(':visible')){
                        window.hWin.HEURIST4.msg.showMsgFlash('Please define the first relationship before adding another', 2000);                        
                    }
                    
                    this.element.find('.rel_link').show();
                    
                    return;
                }

            /* IJ asks to show button                 
            if( this.element.find('.link-div').length>0){ //hide this button if there are links
                $inputdiv.find('.rel_link').hide();
            }else{
                $inputdiv.find('.rel_link').show();
            }                
            */
                

        }
        else if(this.detailType=='resource' && this.configMode.entity=='records'){

            /*
            if(value=='' && this.element.find('.sel_link2').is(':visible')){
                window.hWin.HEURIST4.msg.showMsgFlash('Please select record before adding another pointer',2000);
                return;
            }
            */
            
            var isparententity = (that.f('rst_CreateChildIfRecPtr')==1);
            
            //replace input with div
            $input = $( "<div>").css({'display':'inline-block','vertical-align':'middle','min-wdith':'25ex'})
                            .uniqueId().appendTo( $inputdiv );
            
            var ptrset = that.f('rst_PtrFilteredIDs');
            if(!$.isArray(ptrset)){
                if(ptrset) ptrset = ptrset.split(',')
                else ptrset = [];
            }            
            var rts = [];
            for (var k=0; k<ptrset.length; k++) {
                if(window.hWin.HEURIST4.rectypes.names[ptrset[k]]){
                    rts.push(window.hWin.HEURIST4.rectypes.names[ptrset[k]]);
                }
            }
            rts = (rts.length>0)?rts.join(', '):'record';
            
            //define explicit add relationship button
            $( "<button>", {title: "Select record to be linked"})
                        .button({icon:"ui-icon-triangle-1-e",
                               label:('&nbsp;&nbsp;&nbsp;select'+(isparententity?' child':'')+' '+rts)})
                        .addClass('sel_link2 truncate').css('max-width','300px')
                        .appendTo( $inputdiv );
            
            
            var popup_options = {
                            select_mode: (this.configMode.csv==true?'select_multi':'select_single'),
                            select_return_mode: 'recordset',
                            edit_mode: 'popup',
                            selectOnSave: true, //it means that select popup will be closed after add/edit is completed
                            title: window.hWin.HR((isparententity)
                                ?'CHILD record pointer: select or create a linked child record'
                                :'Record pointer: Select or create a linked record'),
                            rectype_set: that.f('rst_PtrFilteredIDs'),
                            parententity: (isparententity)?that.options.recID:0,
                            
                            onselect:function(event, data){
                                     if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                                        var recordset = data.selection;
                                        var record = recordset.getFirstRecord();
                                        
                                        var rec_Title = recordset.fld(record,'rec_Title');
                                        if(window.hWin.HEURIST4.util.isempty(rec_Title)){
                                            // no proper selection 
                                            // consider that record was not saved - it returns FlagTemporary=1 
                                            return;
                                        }
                                       
                                        var targetID = recordset.fld(record,'rec_ID');
                                        var rec_Title = recordset.fld(record,'rec_Title');
                                        var rec_RecType = recordset.fld(record,'rec_RecTypeID');
                                        that.newvalues[$input.attr('id')] = targetID;
                                        
                                        //window.hWin.HEURIST4.ui.setValueAndWidth($input, rec_Title);
                                        
                                        //save last 25 selected records
                                        var now_selected = data.selection.getIds(25);
                                        window.hWin.HAPI4.save_pref('recent_Records', now_selected, 25);      
                                        
                                        
                                        $input.empty();
                                        var ele = window.hWin.HEURIST4.ui.createRecordLinkInfo($input, 
                                            {rec_ID: targetID, 
                                             rec_Title: rec_Title, 
                                             rec_RecTypeID: rec_RecType,
                                             rec_IsChildRecord:isparententity
                                            }, __show_select_dialog);
                                        //ele.appendTo($inputdiv);
                                        that._onChange();
                                        
                /* 2017-11-08 no more buttons
                                        if($inputdiv.find('.sel_link').length==0){
                                            var $btn_rec_edit_dialog = $( "<span>", {title: "Click select a record to be linked"})
                                                .addClass('smallicon sel_link ui-icon ui-icon-pencil')
                                                        .insertAfter( $input );
                                                        
                                            that._on( $btn_rec_edit_dialog, { click:  __show_select_dialog} ); 
                                        }else{
                                            $inputdiv.find('.sel_link').css({display:'inline-block'});
                                        }
                */                                           
                                        
                                        if( $inputdiv.find('.link-div').length>0 ){ //hide this button if there are links
                                            $input.show();
                                            $inputdiv.find('.sel_link2').hide(); 
                                        }else{
                                            $input.hide();
                                            $inputdiv.find('.sel_link2').show();
                                        }
                                        
                                     }
                            }
            };


            // event is false for confirmation of select mode for parententity
            // 
            var __show_select_dialog = function(event){
                
                    if(that.is_disabled) return;
                
                    if(event!==false){
                
                        if(event) event.preventDefault();
             
                        if(popup_options.parententity>0){
                            
                            if(that.newvalues[$input.attr('id')]>0){
                                
                                window.hWin.HEURIST4.msg.showMsgFlash('Points to a child record; value cannot be changed (delete it or edit the child record itself)', 2500);
                                return;
                            }
                            //__show_select_dialog(false); 
                        }
                    }
                    
                    var usrPreferences = window.hWin.HAPI4.get_prefs_def('select_dialog_'+that.configMode.entity, 
                        {width: null,  //null triggers default width within particular widget
                        height: (window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95 });
        
                    popup_options.width = Math.max(usrPreferences.width,710);
                    popup_options.height = usrPreferences.height;
                    
                    //init related/liked records selection dialog
                    window.hWin.HEURIST4.ui.showEntityDialog(that.configMode.entity, popup_options);
            }

            that._findAndAssignTitle($input, value, __show_select_dialog);
            
            if(value>0){
                /* 2017-11-08 no more button - use icon at the beginning of input 
                        var $btn_rec_search_dialog = $( "<span>", {title: "Click to search and select"})
                            .addClass('smallicon sel_link ui-icon ui-icon-pencil')
                                    .insertAfter( $input );
                            //.button({icons:{primary: 'ui-icon-pencil'},text:false}); //wasui-icon-link
                        this._on( $btn_rec_search_dialog, { click: __show_select_dialog } );
               */         
            }
            
            this._on( $inputdiv.find('.sel_link2'), { click: __show_select_dialog } );

            /* IJ asks to disable this feature 
            if( this.inputs.length>0 || this.element.find('.link-div').length>0){ //hide this button if there are links
                $inputdiv.find('.sel_link2').hide();
            }else{
                $inputdiv.find('.sel_link2').show();
            }
            
            //open dialog for second and further            
            if(this.inputs.length>0 && !(value>0))  __show_select_dialog();
            */
            
            this.newvalues[$input.attr('id')] = value;  //for this type assign value at init  


            
        } 
        
        else if(this.detailType=='resource')
        {
            
            //replace input with div
            $input = $( "<div>").css({'display':'inline-block','vertical-align':'middle','min-wdith':'25ex'})
                            .uniqueId().appendTo( $inputdiv );
                            
                            
            //define explicit add relationship button
            $( "<button>", {title: "Select"})
                        .button({icon:"ui-icon-triangle-1-e",
                               label:('&nbsp;&nbsp;&nbsp;select')})
                        .addClass('sel_link2').hide()
                        .appendTo( $inputdiv );
            
            var $input_img, $gicon;
            var select_return_mode = 'ids';
            
            var icon_for_button = 'ui-icon-pencil'; //was -link
            if(this.configMode.select_return_mode &&
               this.configMode.select_return_mode!='ids'){
                 select_return_mode = 'recordset'
            }
                
            $gicon = $('<span class="ui-icon ui-icon-triangle-1-e sel_link" '
            +'style="display:inline-block;vertical-align:top;margin-left:8px;padding-top:8px;cursor:hand"></span>')
            .insertBefore( $input );
            
            $input.addClass('entity_selector').css({'margin-left': '-24px'});

            $input.css({'min-wdith':'22ex'});

            var ptrset = that.f('rst_PtrFilteredIDs');

            var __show_select_dialog = null;
            /* 2017-11-08 no more buttons
            var $btn_rec_search_dialog = $( "<span>", {title: "Click to search and select"})
            .addClass('smallicon ui-icon '+icon_for_button)
            .appendTo( $inputdiv );
            */
            //.button({icons:{primary: icon_for_button},text:false});
             
            var popup_options = {
                isdialog: true,
                select_mode: (this.configMode.csv==true?'select_multi':'select_single'),
                select_return_mode:select_return_mode, //ids or recordset(for files)
                filter_group_selected:null,
                filter_groups: this.configMode.filter_group,
                onselect:function(event, data){

                 if(data){

                        if(select_return_mode=='ids'){
                            
                            
                            var newsel = window.hWin.HEURIST4.util.isArrayNotEmpty(data.selection)?data.selection:[];
                            
                            //config and data are loaded already, since dialog was opened
                            that._findAndAssignTitle($input, newsel);
                            that.newvalues[$input.attr('id')] = newsel.join(',');
                            that._onChange();
                            
                        }else if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                            //todo

                        }
                 }//data

                }
            };//popup_options
                        

            $input.hide();
            that._findAndAssignTitle($input, value);

            __show_select_dialog = function(event){
                
                    if(that.is_disabled) return;

                    event.preventDefault();
                    
                    var usrPreferences = window.hWin.HAPI4.get_prefs_def('select_dialog_'+this.configMode.entity, 
                        {width: null,  //null triggers default width within particular widget
                        height: (window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95 });
        
                    popup_options.width = usrPreferences.width;
                    popup_options.height = usrPreferences.height;
                    var sels = this.newvalues[$input.attr('id')];//$(event.target).attr('id')];
                    /*if(!sels && this.options.values && this.options.values[0]){
                         sels = this.options.values[0];
                    }*/ 
                    
                    if(!window.hWin.HEURIST4.util.isempty(sels)){
                        popup_options.selection_on_init = sels.split(',');
                    } else {
                        popup_options.selection_on_init = null;    
                    }                                                                                       
                    
                    //init dialog to select related entities
                    window.hWin.HEURIST4.ui.showEntityDialog(this.configMode.entity, popup_options);
            }
            
            
            //no more buttons this._on( $btn_rec_search_dialog, { click: __show_select_dialog } );
            this._on( $input, { keypress: __show_select_dialog, click: __show_select_dialog } );
            this._on( $gicon, { click: __show_select_dialog } );
            this._on( $inputdiv.find('.sel_link2'), { click: __show_select_dialog } );
            
            this.newvalues[$input.attr('id')] = value;  //for this type assign value at init  

        }
        else{
            $input = $( "<input>")
            .uniqueId()
            .addClass('text ui-widget-content ui-corner-all')
            .val(value)
            .keyup(function(){that._onChange();})
            .change(function(){
                    that._onChange();
            })
            .appendTo( $inputdiv );
            
            if(!(this.options.dtID=='file' || this.detailType=='resource' || 
                 this.detailType=='date' || this.detailType=='geo' || this.detailType=='action')){
                     
                $input.keydown(function(e){  //Ctrl+A - select all
                    if (e.keyCode == 65 && e.ctrlKey) {
                                        e.target.select()
                    }    
                });
                if(this.detailType=='password'){
                    $input.prop('type','password');
                }
            }
            
            if(this.options.dtID=='rec_URL' || this.detailType=='url'){
                
                    var $btn_extlink = null, $btn_editlink = null;
                
                    function __url_input_state(force_edit){
                    
                        if($input.val()=='' || force_edit===true){
                            $input.removeClass('rec_URL').addClass('text').removeAttr("readonly");
                            that._off( $input, 'click');
                            if(!window.hWin.HEURIST4.util.isnull( $btn_extlink)){
                                $btn_extlink.remove();
                                $btn_editlink.remove();
                                $btn_extlink = null;
                                $btn_editlink = null;
                            }
                            if(force_edit===true){
                                $input.focus();   
                            }
                        }else if(window.hWin.HEURIST4.util.isnull($btn_extlink)){
                            
                            if($input.val()!='' && !($input.val().indexOf('http://')==0 || $input.val().indexOf('https://')==0)){
                                $input.val( 'http://'+$input.val());
                            }
                            $input.addClass('rec_URL').removeClass('text').attr('readonly','readonly');
                            
                            $btn_extlink = $( '<span>', {title: 'Open URL in new window'})
                                .addClass('smallicon ui-icon ui-icon-extlink')
                                .appendTo( $inputdiv );
                                //.button({icons:{primary: 'ui-icon-extlink'},text:false});
                        
                            that._on( $btn_extlink, { click: function(){ window.open($input.val(), '_blank') }} );
                            that._on( $input, { click: function(){ window.open($input.val(), '_blank') }} );

                            $btn_editlink = $( '<span>', {title: 'Edit URL'})
                                .addClass('smallicon ui-icon ui-icon-pencil')
                                .appendTo( $inputdiv );
                                //.button({icons:{primary: 'ui-icon-pencil'},text:false});
                        
                            that._on( $btn_editlink, { click: function(){ __url_input_state(true) }} );
                        }
                
                    }
                
                    $input.focusout( __url_input_state ); 
                    
                    __url_input_state();               
                
            }
            else if(this.detailType=="integer" || this.detailType=="year"){

                $input.keypress(function (e) {
                    var code = e.charCode || e.keyCode;
                    var charValue = String.fromCharCode(code);
                    var valid = false;

                    if(charValue=='-' && this.value.indexOf('-')<0){
                        this.value = '-'+this.value;
                    }else{
                        valid = /^[0-9]+$/.test(charValue);
                    }

                    if(!valid){
                        window.hWin.HEURIST4.util.stopEvent(e);
                        e.preventDefault();
                        window.hWin.HEURIST4.msg.showTooltipFlash(window.hWin.HR('Numeric field'),1000,$input);
                    }

                });
                /*$input.keyup(function () {
                if (this.value != this.value.replace(/[^0-9-]/g, '')) {
                this.value = this.value.replace(/[^0-9-]/g, '');  //[-+]?\d
                }
                });*/
            }else
            if(this.detailType=="float"){

                    $input.keypress(function (e) {
                        var code = e.charCode || e.keyCode; //(e.keyCode ? e.keyCode : e.which);
                        var charValue = String.fromCharCode(code);
                        var valid = false;

                        if(charValue=='-' && this.value.indexOf('-')<0){
                            this.value = '-'+this.value;
                        }else if(charValue=='.' && this.value.indexOf('.')<0){
                            valid = true;
                        }else{
                            valid = /^[0-9]+$/.test(charValue);
                        }

                        if(!valid){
                            window.hWin.HEURIST4.util.stopEvent(e);
                            e.preventDefault();
                            window.hWin.HEURIST4.msg.showTooltipFlash(window.hWin.HR('Numeric field'),1000,$input);
                        }

                    });

            }else
            if(this.detailType=='date'){
                
                $input.css('width', this.options.is_faceted_search?'13ex':'20ex');

                function __onDateChange(){
                            var value = $input.val();
                            
                            that.newvalues[$input.attr('id')] = value; 
                            
                            var isTemporalValue = value && value.search(/\|VER/) != -1; 
                            if(isTemporalValue) {
                                window.hWin.HEURIST4.ui.setValueAndWidth($input, temporalToHumanReadableString(value));    
                                
                                $input.addClass('Temporal').removeClass('text').attr('readonly','readonly');
                            }else{
                                $input.removeClass('Temporal').addClass('text').removeAttr("readonly").css('width','20ex');
                            }
                            
                            that._onChange();
                }
                
                
                if($.isFunction($('body').calendarsPicker)){ //third party picker - NOT IN USE
                        
                    var defDate = window.hWin.HAPI4.get_prefs("record-edit-date");
                    $input.calendarsPicker({
                        calendar: $.calendars.instance('gregorian'),
                        showOnFocus: false,
                        defaultDate: defDate?defDate:'',
                        selectDefaultDate: true,
                        dateFormat: 'yyyy-mm-dd',
                        pickerClass: 'calendars-jumps',
                        //popupContainer: $input.parents('body'),
                        onSelect: function(dates){
                        },
                        renderer: $.extend({}, $.calendars.picker.defaultRenderer,
                                {picker: $.calendars.picker.defaultRenderer.picker.
                                    replace(/\{link:prev\}/, '{link:prevJump}{link:prev}').
                                    replace(/\{link:next\}/, '{link:nextJump}{link:next}')}),
                        showTrigger: '<span class="ui-icon ui-icon-calendar trigger" style="display:inline-block" alt="Popup"></span>'}
                    );     
                           
                }else{                                       // we use jquery datepicker
                        var $datepicker = $input.datepicker({
                            /*showOn: "button",
                            buttonImage: "ui-icon-calendar",
                            buttonImageOnly: true,*/
                            showButtonPanel: true,
                            changeMonth: true,
                            changeYear: true,
                            dateFormat: 'yy-mm-dd',
                            beforeShow: function(){
                                
                                if(that.is_disabled) return false;
                                
                                var prev_dp_value = window.hWin.HAPI4.get_prefs('edit_record_last_entered_date'); 
                                if($input.val()=='' && !window.hWin.HEURIST4.util.isempty(prev_dp_value)){
                                    //$datepicker.datepicker( "setDate", prev_dp_value );    
                                    $datepicker.datepicker( "option", "defaultDate", prev_dp_value); 
                                }
                            
                            },
                            onClose: function(dateText, inst){
                                __onDateChange();
                                
                                if($input.val()!='')
                                    window.hWin.HAPI4.save_pref('edit_record_last_entered_date', $input.val());
                                //$input.change();
                            }
                            /*,beforeShow : function(dateText, inst){
                                $(inst.dpDiv[0]).find('.ui-datepicker-current').click(function(){
                                    console.log('today '+$datepicker.datepicker( 'getDate' ));  
                                });
                            }*/
                        });

                        var $btn_datepicker = $( '<span>', {title: 'Show calendar'})
                            .addClass('smallicon ui-icon ui-icon-calendar')
                            .appendTo( $inputdiv );
                        //.button({icons:{primary: 'ui-icon-calendar'},text:false});
                       
                        
                        this._on( $btn_datepicker, { click: function(){
                            
                                if(that.is_disabled) return;

                                $datepicker.datepicker( 'show' ); 
                                $("#ui-datepicker-div").css("z-index", "999999 !important"); 
                                //$(".ui-datepicker").css("z-index", "999999 !important");   
                        }} );
                } 

                if(this.options.dtID>0){ //this is details of records
                
                    if(this.options.is_faceted_search){
                        $input.css({'max-width':'13ex','min-width':'13ex'});
                    }else{
                        var $btn_temporal = $( '<span>', 
                            {title: 'Pop up widget to enter compound date information (uncertain, fuzzy, radiometric etc.)'})
                        .addClass('smallicon ui-icon ui-icon-clock')
                        .appendTo( $inputdiv );
                        //.button({icons:{primary: 'ui-icon-clock'}, text:false});
                    }
                    
                    this._on( $btn_temporal, { click: function(){
                        
                                if(that.is_disabled) return;

                                var url = window.hWin.HAPI4.baseURL 
                                    + 'hclient/widgets/editing/editTemporalObject.html?'
                                    + encodeURIComponent(that.newvalues[$input.attr('id')]
                                                ?that.newvalues[$input.attr('id')]:$input.val());
                                
                                window.hWin.HEURIST4.msg.showDialog(url, {height:550, width:750,
                                    title: 'Temporal Object',
                                    //class:'ui-heurist-bg-light',
                                    callback: function(str){
                                        if(!window.hWin.HEURIST4.util.isempty(str) && that.newvalues[$input.attr('id')] != str){
                                            $input.val(str);    
                                            $input.change();
                                        }
                                    }
                                } );
                    
                    }} );
                    
                    $input.change(__onDateChange);
                   
                }//temporal allowed
                else{
                    this.newvalues[$input.attr('id')] = value;  //for this type assign value at init          
                }
                
                $input.val(value);    
                $input.change();   
                                     
            }else 
            if(this.isFileForRecord){
                
                        var $input_img, $gicon;
                        
                        var icon_for_button = 'ui-icon-folder-open';
                        var select_return_mode = 'recordset';
                        
                        $input.css({'padding-left':'30px', cursor:'hand'});
                        //folder icon in the begining of field
                        $gicon = $('<span class="ui-icon ui-icon-folder-open"></span>')
                            .css({position: 'absolute', margin: '5px 0px 0px 8px', cursor:'hand'}).insertBefore( $input ); 
                        
                        //container for image
                        $input_img = $('<div class="image_input ui-widget-content ui-corner-all">'
                        + '<img class="image_input"></div>')
                        .css({'position':'absolute','display':'none','z-index':9999})
                        .appendTo( $inputdiv ); 
                        
                        $input.change(function(){
                            var val = that.newvalues[$input.attr('id')];

                            if(window.hWin.HEURIST4.util.isempty(val) || !(val.ulf_ID >0)){
                                $input.val('');
                            }
                            //clear thumb rollover
                            if(window.hWin.HEURIST4.util.isempty($input.val())){
                                 $input_img.find('img').attr('src','');    
                            }
                            that._onChange(); 
                        });
                        
                        var hideTimer = 0;
                        $input.mouseover(function(){
                            if(!window.hWin.HEURIST4.util.isempty($input_img.find('img').attr('src'))){
                                if (hideTimer) {
                                    window.clearTimeout(hideTimer);
                                    hideTimer = 0;
                                }
                                $input_img.show();
                            }
                        });
                        $input.mouseout(function(){
                            if($input_img.is(':visible')){
                                hideTimer = window.setTimeout(function(){$input_img.hide(1000);}, 500);
                            }});
                            
                        $input.css({'min-wdith':'22ex'});

                        var ptrset = that.f('rst_PtrFilteredIDs');

                        var __show_select_dialog = null;
                        /* 2017-11-08 no more buttons
                        var $btn_rec_search_dialog = $( "<span>", {title: "Click to search and select"})
                        .addClass('smallicon ui-icon '+icon_for_button)
                        .appendTo( $inputdiv );
                        */
                        //.button({icons:{primary: icon_for_button},text:false});
                         
                        var popup_options = {
                            isdialog: true,
                            select_mode: 'select_single',
                            select_return_mode:select_return_mode, //ids or recordset(for files)
                            filter_group_selected:null,
                            filter_groups: this.configMode.filter_group,
                            onselect:function(event, data){

                             if(data){
                                
                                    if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                                        var recordset = data.selection;
                                        var record = recordset.getFirstRecord();
                                        
                                        var newvalue = {ulf_ID: recordset.fld(record,'ulf_ID'),
                                                        ulf_ExternalFileReference: recordset.fld(record,'ulf_ExternalFileReference'),
                                                        ulf_OrigFileName: recordset.fld(record,'ulf_OrigFileName'),
                                                        ulf_ObfuscatedFileID: recordset.fld(record,'ulf_ObfuscatedFileID')};
                                        
                                        that.newvalues[$input.attr('id')] = newvalue;
                                        that._findAndAssignTitle($input, newvalue);
                                        
                                        /*
                                        that.newvalues[$input.attr('id')] = recordset.fld(record,'ulf_ID');
                                        
                                        var rec_Title = recordset.fld(record,'ulf_ExternalFileReference');
                                        if(window.hWin.HEURIST4.util.isempty(rec_Title)){
                                            rec_Title = recordset.fld(record,'ulf_OrigFileName');
                                        }
                                        window.hWin.HEURIST4.ui.setValueAndWidth($input, rec_Title, 10);

                                        //url for thumb
                                        $inputdiv.find('.image_input > img').attr('src',
                                            window.hWin.HAPI4.baseURL + '?db=' + window.hWin.HAPI4.database + '&thumb='+
                                            recordset.fld(record,'ulf_ObfuscatedFileID'));
                                        */
                                        $input.change();
                                    }
                                
                             }//data

                            }
                        };//popup_options
                        

                        that._findAndAssignTitle($input, value);

                        __show_select_dialog = function(event){
                            
                                if(that.is_disabled) return;

                                event.preventDefault();
                                
                                var usrPreferences = window.hWin.HAPI4.get_prefs_def('select_dialog_'+this.configMode.entity, 
                                    {width: null,  //null triggers default width within particular widget
                                    height: (window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95 });
                    
                                popup_options.width = usrPreferences.width;
                                popup_options.height = usrPreferences.height;
                                var sels = this.newvalues[$(event.target).attr('id')];
                                if(!sels && this.options.values && this.options.values[0]){
                                     sels = this.options.values[0];    //take selected value from options
                                } 

                                if($.isPlainObject(sels)){
                                    popup_options.selection_on_init = [sels.ulf_ID];
                                }else if(!window.hWin.HEURIST4.util.isempty(sels)){
                                    popup_options.selection_on_init = sels.split(',');
                                } else {
                                    popup_options.selection_on_init = null;    
                                }                                                                                       
                                //init dialog to select related entities
                                window.hWin.HEURIST4.ui.showEntityDialog(this.configMode.entity, popup_options);
                        }
                        
                        if(__show_select_dialog!=null){
                            //no more buttons this._on( $btn_rec_search_dialog, { click: __show_select_dialog } );
                            this._on( $input, { keypress: __show_select_dialog, click: __show_select_dialog } );
                            this._on( $gicon, { click: __show_select_dialog } );
                        }
                        
                        if(this.isFileForRecord && value){
                            //assign value at once
                            this.newvalues[$input.attr('id')] = value;
                            /*
                            if($.isPlainObject(value) && value.ulf_ID>0){
                                this.newvalues[$input.attr('id')] = value.ulf_ID;   
                            }else if (parseInt(value)>0){
                                this.newvalues[$input.attr('id')] = value;
                            }*/
                        }
                
            }
            else
            if( this.detailType=='folder' ){
                
                $input.css({'padding-left':'30px'});
                
                var $gicon = $('<span>').addClass('ui-icon ui-icon-gear')
                    .css({position:'absolute',margin:'2px 0 0 8px',cursor:'hand'})
                    .insertBefore($input);
                var $select_folder_dlg = $('<div/>').hide().appendTo( $inputdiv )
                
                that.newvalues[$input.attr('id')] = value;
                    
                this._on( $gicon, { click: function(){                                 
                        $select_folder_dlg.select_folders({
                       onselect:function(newsel){
                            if(newsel){
                                var newsel = newsel.join(';');
                                that.newvalues[$input.attr('id')] = newsel;
                                $input.val(newsel);
                                that._onChange();
                            }
                        }, 
                       selectedFolders: that.newvalues[$input.attr('id')], 
                       multiselect: that.configMode && that.configMode.multiselect});
                    }} );
            }
            else
            if( this.detailType=='file' ){
                
                
                        this.options.showclear_button = (this.configMode.hideclear!=1);
                        
                        if(!this.configMode.version) this.configMode.version = 'thumb';
                
                        //url for thumb
                        var urlThumb = window.hWin.HAPI4.getImageUrl(this.configMode.entity, 
                                                        this.options.recID, this.configMode.version, 1);
                        var dt = new Date();
                        urlThumb = urlThumb+'&ts='+dt.getTime();
                        
                        $input.css({'padding-left':'30px'});
                        $('<span class="ui-icon ui-icon-folder-open"></span>')
                                .css({position: 'absolute', margin: '5px 0px 0px 8px'}).insertBefore( $input ); 
                        
                        //container for image
                        var $input_img = $('<div tabindex="0" contenteditable class="image_input fileupload ui-widget-content ui-corner-all" style="border:dashed blue 2px">'
                            + '<img src="'+urlThumb+'" class="image_input">'
                            + '</div>').appendTo( $inputdiv );                
                       if(this.configMode.entity=='recUploadedFiles'){
                           $input_img.css({'min-height':'320px','min-width':'320px'});
                           $input_img.find('img').css({'max-height':'320px','max-width':'320px'});
                       }
                         
                        window.hWin.HAPI4.checkImageUrl(this.configMode.entity, this.options.recID, 
                            this.configMode.version,
                            function(response){
                                  if(response.data=='ok'){
                                      that.entity_image_already_uploaded = true;
                                  }
                        });
                        
                        //change parent div style - to allow special style for image selector
                        if(that.configMode.css){
                            that.element.css(that.configMode.css);
                        }
                        
                        //library browser and explicit file upload buttons
                        if(that.configMode.use_assets){
                            var ele = $('<div style="display:inline-block;vertical-align:top;padding-left:4px"/>')
                            .appendTo( $inputdiv );                            
                            
                            $('<a href="#"><span class="ui-icon ui-icon-folder-open"/>Upload file</a>')
                                .click(function(){ $input.click() }).appendTo( ele );                            
                            $('<br/>').appendTo( ele );                            
                            
                            $('<a href="#" title="Or select from library"><span class="ui-icon ui-icon-grid"/>Library</a>')
                                .click(function(){                                 
                                    $select_imagelib_dlg.select_imagelib({onselect:function(res){
                                        if(res){
                                            $input_img.find('img').prop('src', res.url);
                                            that.newvalues[$input.attr('id')] = res.path; 
                                            that._onChange(); 
                                        }
                                    }, assets:that.configMode.use_assets, size:that.configMode.size});
                                }).appendTo( ele );                            
                        }
                            
                /* 2017-11-08 no more buttons 
                        //browse button    
                        var $btn_fileselect_dialog = $( "<span>", {title: "Click to select file for upload"})
                        .addClass('smallicon fileupload ui-icon ui-icon-folder-open')
                        .css('vertical-align','top')
                        .appendTo( $inputdiv );
                        //.button({icons:{primary: "ui-icon-folder-open"},text:false});
                  */                      
                        //set input as file and hide
                        $input.prop('type','file').hide();
                        
                        //temp file name  it will be renamed on server to recID.png on save
                        var newfilename = '~'+window.hWin.HEURIST4.util.random();

                        //crate progress dialog
                        var $progress_dlg = $('<div title="File Upload"><div class="progress-label">Starting upload...</div>'
                        +'<div class="progressbar" style="margin-top: 20px;"></div></div>').hide().appendTo( $inputdiv );
                        var $progress_bar = $progress_dlg.find('.progressbar');
                        var $progressLabel = $progress_dlg.find('.progress-label');
                        
                        var $select_imagelib_dlg = $('<div/>').hide().appendTo( $inputdiv );//css({'display':'inline-block'}).
         
                        $progress_bar.progressbar({
                              value: false,
                              change: function() {
                                $progressLabel.text( "Current Progress: " + $progress_bar.progressbar( "value" ) + "%" );
                              },
                              complete: function() {
                                    $progressLabel.text( "Complete!" );
                              }
                          });
         
                        //init upload widget
                        $input.fileupload({
    url: window.hWin.HAPI4.baseURL +  'hsapi/utilities/fileUpload.php',  //'external/jquery-file-upload/server/php/',
    //url: 'templateOperations.php',
    formData: [ {name:'db', value: window.hWin.HAPI4.database}, 
                {name:'entity', value:this.configMode.entity},
                {name:'version', value:this.configMode.version},
                {name:'maxsize', value:this.configMode.size},
                {name:'registerAtOnce', value:this.configMode.registerAtOnce},
                {name:'recID', value:that.options.recID}, //need to verify permissions
                //{name:'DBGSESSID', value:'425944380594800002;d=1,p=0,c=07'},
                {name:'newfilename', value:newfilename }], //unique temp name
    //acceptFileTypes: /(\.|\/)(gif|jpe?g|png)$/i
    //autoUpload: true,
    sequentialUploads:true,
    dataType: 'json',
    pasteZone: $input_img,
    dropZone: $input_img,
    // add: function (e, data) {  data.submit(); },
    submit: function (e, data) { //start upload
    
        $progress_dlg = $progress_dlg.dialog({
            autoOpen: false,
            modal: true,
            closeOnEscape: false,
            resizable: false,
            buttons: []
          });                        
        $progress_dlg.dialog('open'); 
        $progress_dlg.parent().find('.ui-dialog-titlebar-close').hide();
    },
    done: function (e, response) {
            //hide progress bar
            $progress_dlg.dialog( "close" );
        
            if(response.result){//????
                response = response.result;
            }
            if(response.status==window.hWin.ResponseStatus.OK){
                var data = response.data;

                $.each(data.files, function (index, file) {
                    if(file.error){ //it is not possible we should cought it on server side - just in case
                        $input_img.find('img').prop('src', '');
                        window.hWin.HEURIST4.msg.showMsgErr(file.error);
                    }else{

                        if(file.ulf_ID>0){ //file is registered at once and it returns ulf_ID
                            that.newvalues[$input.attr('id')] = file.ulf_ID;
                        }else{
                            
                            //var urlThumb = window.hWin.HAPI4.getImageUrl(that.configMode.entity, 
                            //            newfilename+'.png', 'thumb', 1);
                            var urlThumb =
                            (that.configMode.entity=='recUploadedFiles'
                                ?file.url
                                :file[(that.configMode.version=='icon')?'iconUrl':'thumbnailUrl'])
                                +'?'+(new Date()).getTime();
                            
                            // file.thumbnailUrl - is correct but inaccessible for heurist server
                            // we get image via fileGet.php
                            $input_img.find('img').prop('src', '');
                            $input_img.find('img').prop('src', urlThumb);
                            
                            if(that.configMode.entity=='recUploadedFiles'){
                                that.newvalues[$input.attr('id')] = file;
                            }else{
                                that.newvalues[$input.attr('id')] = newfilename;  //keep only tempname
                            }
                        }
                        $input.attr('title', file.name);
                        that._onChange();//need call it manually since onchange event is redifined by fileupload widget
                    }
                });
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);// .message
            }
            var inpt = this;
            $input_img.off('click');
            $input_img.on({click: function(){
                        $(inpt).click();
            }});
            },                            
    progressall: function (e, data) { //@todo to implement
        var progress = parseInt(data.loaded / data.total * 100, 10);
//console.log(progress + '%  '+data.loaded + ' of ' + data.total);
        //$('#progress .bar').css('width',progress + '%');
        $progress_bar.progressbar( "value", progress );        
    }                            
                        });
                
                        //init click handlers
                        //this._on( $btn_fileselect_dialog, { click: function(){ $input_img.click(); } } );
                        $input_img.on({click: function(e){ //find('a')
                            $input.click(); //open file browse
                            
                            if($(e.target).is('img')){
                            }else{
                            }
                        }});
                        /*focus: function(){
                             $input_img.css({border:'dashed green 2px'});
                        },
                        blur: function(){
                             $input_img.css({border:'none'});
                        }});*
/*                        
                        paste:function(e){
console.log('onpaste');                            

    var items = e.originalEvent.clipboardData.items;
    for (var i = 0 ; i < items.length ; i++) {
        var item = items[i];
        if (item.type.indexOf("image") >=0) {
            console.log("FILE!");
        } else {
            console.log("Ignoring non-image.");
        }
    }
                        }});
*/                        
                        
                        
            }
            else //------------------------------------------------------------------------------------
            if(this.detailType=='action'){
                
                $input.css({'width':'62ex','padding-left':'30px',cursor:'hand'});
                   
                var $gicon = $('<span>').addClass('ui-icon ui-icon-gear')
                    .css({position:'absolute',margin:'2px 0 0 8px',cursor:'hand'})
                    .insertBefore($input);
            
                //parse and return json object
                that.newvalues[$input.attr('id')] = window.hWin.HEURIST4.util.isJSON(value);
                if(that.newvalues[$input.attr('id')]==false){
                    that.newvalues[$input.attr('id')] = {};
                }
                $input.val(JSON.stringify(that.newvalues[$input.attr('id')])).css('cursor','hand');
                
                      
                __show_action_dialog = function (event){
                        event.preventDefault();
                        
                        if(that.is_disabled) return;
                        
                        var dlg_options = that.newvalues[$input.attr('id')];
                        if(  window.hWin.HEURIST4.util.isempty(dlg_options) ){
                            dlg_options = {};
                        }
                        dlg_options.title = that.configMode.title;
                        dlg_options.get_params_only = true;
                        dlg_options.onClose = function(value){
                            if(value){
                                that.newvalues[$input.attr('id')] = window.hWin.HEURIST4.util.isJSON(value);
                                if(that.newvalues[$input.attr('id')]==false){
                                    that.newvalues[$input.attr('id')] = {};
                                }
                                $input.val(JSON.stringify(that.newvalues[$input.attr('id')])).change();
                            }
                        };
                        
                        window.hWin.HEURIST4.ui.showRecordActionDialog( this.configMode.actionName, dlg_options );
                };

                this._on( $input, { keypress: __show_action_dialog, click: __show_action_dialog } );
                this._on( $gicon, { click: __show_action_dialog } );
            }else //-----------------------------------------------
            if(this.detailType=='geo'){
                
                $input.css({'width':'62ex','padding-left':'30px',cursor:'hand'});
                   
                var $gicon = $('<span>').addClass('ui-icon ui-icon-globe')
                    .css({position:'absolute',margin:'5px 0 0 8px',cursor:'hand'})
                    .insertBefore($input);
                
                /* 2017-11-08 no more buttons
                var $btn_digitizer_dialog = $( "<span>", {title: "Click to draw map location"})
                        .addClass('smallicon ui-icon ui-icon-pencil')
                        .appendTo( $inputdiv );
                        //.button({icons:{primary: "ui-icon-pencil"},text:false});
                var $link_digitizer_dialog = $( '<a>', {title: "Click to draw map location"})
                        .text( window.hWin.HR('Edit'))
                        .css('cursor','pointer')
                        .appendTo( $inputdiv );
                */
                var geovalue = window.hWin.HEURIST4.geo.wktValueToDescription(value);
            
                that.newvalues[$input.attr('id')] = value;
                $input.val(geovalue.type+'  '+geovalue.summary).css('cursor','hand');
                      
                __show_mapdigit_dialog = function (event){
                        event.preventDefault();
                        
                        if(that.is_disabled) return;
                    
                        var url = window.hWin.HAPI4.baseURL +
                        'viewers/map/mapDraw.php?db='+window.hWin.HAPI4.database+
                        '&wkt='+that.newvalues[$input.attr('id')]; //$input.val();
                       
                       var wkt_params = {'wkt': that.newvalues[$input.attr('id')] };
                        
                        window.hWin.HEURIST4.msg.showDialog(url, {height:'900', width:'1000',
                            window: window.hWin,  //opener is top most heurist window
                            dialogid: 'map_digitizer_dialog',
                            params: wkt_params,
                            title: window.hWin.HR('Heurist map digitizer'),
                            class:'ui-heurist-bg-light',
                            callback: function(location){
                                if( !window.hWin.HEURIST4.util.isempty(location) ){
                                    //that.newvalues[$input.attr('id')] = location
                                    that.newvalues[$input.attr('id')] = location.type+' '+location.wkt;
                                    var geovalue = window.hWin.HEURIST4.geo.wktValueToDescription(location.type+' '+location.wkt);
                                    $input.val(geovalue.type+'  '+geovalue.summary).change();
                                    //$input.val(location.type+' '+location.wkt)
                                }
                            }
                        } );
                };

                //this._on( $link_digitizer_dialog, { click: __show_mapdigit_dialog } );
                //this._on( $btn_digitizer_dialog, { click: __show_mapdigit_dialog } );
                this._on( $input, { keypress: __show_mapdigit_dialog, click: __show_mapdigit_dialog } );
                this._on( $gicon, { click: __show_mapdigit_dialog } );
            }
            /*else if(this.detailType=="freetext" && this.options['input_width']){
            $input.css('width', this.options['input_width']);
            }*/

        }
        
        var dwidth = this.f('rst_DisplayWidth');
        
        if( typeof dwidth==='string' && dwidth.indexOf('%')== dwidth.length-1){ //set in percents
            
            $input.css('width', dwidth);
            
        }else if ( this.detailType!='boolean' && this.detailType!='date' && this.detailType!='resource' ) {  
              //if the size is greater than zero
              if (parseFloat( dwidth ) > 0) 
                  $input.css('width', Math.round(2 + Math.min(120, Number(dwidth))) + "ex"); //was *4/3
              
        }
        if(this.detailType!='blocktext')
            $input.css('max-width', '600px');

        this.inputs.push($input);

        //name="type:1[bd:138]"

        //clear button
        //var $btn_clear = $( "<div>")
        if(this.options.showclear_button && this.options.dtID!='rec_URL')
        {

            var $btn_clear = $('<span>')
            .addClass("smallbutton ui-icon ui-icon-circlesmall-close")//   ui-icon
            .attr('tabindex', '-1')
            .attr('title', 'Clear entered value')
            .attr('data-input-id', $input.attr('id'))
            .appendTo( $inputdiv )
            //.button({icons:{primary: "ui-icon-circlesmall-close"},text:false});
            .css({position: 'absolute', 'margin-top': '3px',
                 cursor:'pointer',             //'font-size':'2em',
//outline_suppress does not work - so list all these props here explicitely                
                    outline: 'none','outline-style':'none', 'box-shadow':'none',  'border-color':'transparent'
            });
            
            // bind click events
            this._on( $btn_clear, {
                click: function(e){

                    if(that.is_disabled) return;

                    var input_id = $(e.target).attr('data-input-id');  //parent(). need if button
                    
                    if(that.detailType=="resource" && that.configMode.entity=='records' 
                            && that.f('rst_CreateChildIfRecPtr')==1){
                        that._clearChildRecordPointer( input_id );
                    }else{
                        that._removeInput( input_id );
                    }
                    
                    that._onChange(); 
                }
            });

        }

        return $input.attr('id');

    }, //addInput
    
    //
    //
    //
    _clearChildRecordPointer: function( input_id ){
        
            var that = this;
        
            var popele = that.element.find('.child_delete_dlg');
            if(popele.length==0){
                var sdiv = '<div class="child_delete_dlg">'
                +'<div style="padding:15px 0">You are deleting a pointer to a child record, that is a record which is owned by/an integral part of the current record, as identified by a pointer back from the child to the current record.</div>'
                //Actions:<br>
                +'<div><label><input type="radio" value="1" name="delete_mode" style="outline:none"/>'
                            +'Delete parent pointer in the child record</label><br><br>'
                        +'<label><input type="radio" value="2" name="delete_mode" checked="checked" style="outline:none"/>'
                            +'Delete the child record completely</label></div>'
                +'<div style="padding:15px 0">Warning: If you delete the parent pointer in the child record, this will generally render the record useless as it will lack identifying information.</div></div>';
                
//<label><input type="radio" value="0" name="delete_mode"/>Leave child record as-is</label><br>
//<p style="padding:0 0 15px 0">If you leave the child record as-is, it will remain as a child of the current record and retain a pointer allowing the parent record information to be used in the child\'s record title, custom reports etc.</p>                
                popele = $(sdiv).appendTo(that.element);
            }
            
            var $dlg_pce = null;
            
            var btns = [
                    {text:window.hWin.HR('Proceed'),
                          click: function() { 
                          
                          var mode = popele.find('input[name="delete_mode"]:checked').val();     
                          if(mode==2){
                              //remove child record
                              var child_rec_to_delete = that.newvalues[input_id];
                              window.hWin.HAPI4.RecordMgr.remove({ids: child_rec_to_delete}, 
                                function(response){
                                    if(response.status == window.hWin.ResponseStatus.OK){
                                        
                                        var delcnt = response.data.deleted.length, msg = '';
                                        if(delcnt>1){
                                            msg = delcnt + ' records have been removed.';
                                            if(response.data.bkmk_count>0 || response.data.rels_count>0){
                                               msg = ' as well as '+
                                                (response.data.bkmk_count>0?(response.data.bkmk_count+' bookmarks'):'')+' '+
                                                (response.data.rels_count>0?(response.data.rels_count+' relationships'):'');
                                            }
                                        }else{
                                            msg = 'Child record has been removed';
                                        }
                                        window.hWin.HEURIST4.msg.showMsgFlash(msg, 2500);
                                        
                                        that._removeInput( input_id );
                                    }
                                });
                          } else {
                              that._removeInput( input_id );
                          }
                          
                          $dlg_pce.dialog('close'); 
                    }},
                    {text:window.hWin.HR('Cancel'),
                          click: function() { $dlg_pce.dialog('close'); }}
            ];            
            
            $dlg_pce = window.hWin.HEURIST4.msg.showElementAsDialog({
                window:  window.hWin, //opener is top most heurist window
                title: window.hWin.HR('Child record pointer removal'),
                width: 500,
                height: 300,
                element:  popele[0],
                resizable: false,
                buttons: btns
            });
        
    },

    //
    // assign title of resource record or file name or related entity
    //
    _findAndAssignTitle: function(ele, value, selector_function){
        
        var that = this;
        
        if(this.isFileForRecord){   //FILE FOR RECORD
            
            if(!value){   //empty value
                window.hWin.HEURIST4.ui.setValueAndWidth(ele, '');
                return;
            }
        
            if($.isPlainObject(value) && value.ulf_ObfuscatedFileID){
        
                var rec_Title = value.ulf_ExternalFileReference;
                if(window.hWin.HEURIST4.util.isempty(rec_Title)){
                    rec_Title = value.ulf_OrigFileName;
                }
                window.hWin.HEURIST4.ui.setValueAndWidth(ele, rec_Title, 10);
                
                //url for thumb
                ele.parent().find('.image_input > img').attr('src',
                    window.hWin.HAPI4.baseURL + '?db=' + window.hWin.HAPI4.database + '&thumb='+
                        value.ulf_ObfuscatedFileID);
                        
            }else{
                 //call server for file details
                 var recid = ($.isPlainObject(value))?value.ulf_ID :value;
                 if(recid>0){
                     
                     var request = {};
                        request['recID']  = recid;
                        request['a']          = 'search'; //action
                        request['details']    = 'list';
                        request['entity']     = 'recUploadedFiles';
                        request['request_id'] = window.hWin.HEURIST4.util.random();
                        
                        window.hWin.HAPI4.EntityMgr.doRequest(request,
                            function(response){
                                if(response.status == window.hWin.ResponseStatus.OK){
                                        var recordset = new hRecordSet(response.data);
                                        var record = recordset.getFirstRecord();
                                        if(record){
                                        var newvalue = {ulf_ID: recordset.fld(record,'ulf_ID'),
                                                        ulf_ExternalFileReference: recordset.fld(record,'ulf_ExternalFileReference'),
                                                        ulf_OrigFileName: recordset.fld(record,'ulf_OrigFileName'),
                                                        ulf_ObfuscatedFileID: recordset.fld(record,'ulf_ObfuscatedFileID')};
                                                        that._findAndAssignTitle(ele, newvalue, selector_function);
                                        }
                                }
                            });
                 }
            }
                    
        }else if(this.detailType=='file'){  // FILE FOR OTHER ENTITIES - @todo test
            
            window.hWin.HEURIST4.ui.setValueAndWidth(ele, value, 10);
            
        }else if(this.configMode.entity==='records'){     //RECORD
        
                var isChildRecord = that.f('rst_CreateChildIfRecPtr');
        
                //assign initial display value
                if(Number(value)>0){
                    var sTitle = null;
                    if(that.options.recordset){
                        var relations = that.options.recordset.getRelations();
                        if(relations && relations.headers && relations.headers[value]){
                            
                            sTitle = relations.headers[value][0];
                            
                            ele.empty();
                            window.hWin.HEURIST4.ui.createRecordLinkInfo(ele, 
                                            {rec_ID: value, 
                                             rec_Title: relations.headers[value][0], 
                                             rec_RecTypeID: relations.headers[value][1],
                                             rec_IsChildRecord: isChildRecord,
                                             rec_OwnerUGrpID: relations.headers[value][2],
                                             rec_NonOwnerVisibility: relations.headers[value][3]
                                             },
                                             selector_function);
                                             
                            //window.hWin.HEURIST4.ui.setValueAndWidth(ele, rec_Title);
                        }
                    }
                    if(!sTitle){
                        window.hWin.HAPI4.RecordMgr.search({q: 'ids:'+value, w: "e", f:"header"},  //search for temp also
                            function(response){
                                if(response.status == window.hWin.ResponseStatus.OK){
                                    ele.empty();

                                    var recordset = new hRecordSet(response.data);
                                    if(recordset.length()>0){
                                        var record = recordset.getFirstRecord();
                                        var rec_Title = recordset.fld(record,'rec_Title');
                                        if(!rec_Title) {rec_Title = 'New record. Title is not defined yet.';}
                                        
                                        var rec_RecType = recordset.fld(record,'rec_RecTypeID');
                                        window.hWin.HEURIST4.ui.createRecordLinkInfo(ele, 
                                                {rec_ID: value, 
                                                 rec_Title: rec_Title, 
                                                 rec_RecTypeID: rec_RecType,
                                                 rec_IsChildRecord: isChildRecord
                                                 }, selector_function);
                                                 
                                       ele.show();
                                       ele.parent().find('.sel_link').show();
                                       ele.parent().find('.sel_link2').hide();
                                                 
                                    }else{
                                        that._removeInput( ele.attr('id') );
                                    }
                                    //window.hWin.HEURIST4.ui.setValueAndWidth(ele, rec_Title);
                                }
                            }
                        );
                    }
                    
                    
                }else{
                    window.hWin.HEURIST4.ui.setValueAndWidth(ele, '');
                }
                
                //hide this button if there are links
                if( ele.parent().find('.link-div').length>0 ){ 
                    ele.show();
                    ele.parent().find('.sel_link2').hide();
                }else{
                    ele.hide();
                    ele.parent().find('.sel_link2').show();
                }
                    
                
        }else{    
            //related entity                 
            if(window.hWin.HEURIST4.util.isempty(value)) value = [];
            value = $.isArray(value)?value:value.split(',');
            if(value.length==0){
                ele.empty();
                ele.hide();
                ele.parent().find('.sel_link').hide();
                ele.parent().find('.sel_link2').show();
                
            }else{
                window.hWin.HAPI4.EntityMgr.getTitlesByIds(this.configMode.entity, value,
                   function( display_value ){
                       ele.empty();
                       hasValues = false;
                       if(display_value && display_value.length>0){
                           for(var i=0; i<display_value.length; i++){
                               if(display_value[i]){
                                    $('<div class="link-div">'+display_value[i]+'</div>').appendTo(ele);     
                                    hasValues = true;
                               }
                           }
                       }
                       if(hasValues){
                           ele.show();
                           ele.parent().find('.sel_link').show();
                           ele.parent().find('.sel_link2').hide();
                       }else{
                           ele.hide();
                           ele.parent().find('.sel_link').hide();
                           ele.parent().find('.sel_link2').show();
                       }
                       
                        ///var rec_Title  = display_value.join(',');           
                        //window.hWin.HEURIST4.ui.setValueAndWidth(ele, rec_Title, 10);
                   });
            }
        }
        
    },

    //
    // recreate SELECT for enum/relation type
    //
    _recreateSelector: function($input, value){
        
        if(value===true){
            //keep current
            value = ($input)?$input.val():null;
        }

        if($input) $input.empty();

        var allTerms = this.f('rst_FieldConfig');

        if(!window.hWin.HEURIST4.util.isempty(allTerms)){//this is not vocabulary ID, this is something more complex

            if($.isPlainObject(this.configMode))    { 

                if(this.configMode.entity){ //this lookup for entity
                    
                    //create and fill SELECT
                    //this.configMode.entity
                    //this.configMode.filter_group
                    //if($input==null || $input.length==0) $input = $('<select>').uniqueId();

                    //add add/browse buttons
                    window.hWin.HEURIST4.ui.createEntitySelector($input.get(0), this.configMode, false, null);

                    if(this.configMode.button_browse){

                    }
                
                }else{
                    //type: select, radio, checkbox
                    //hideclear   
                    //values                 
                    $input = window.hWin.HEURIST4.ui.createInputSelect($input, allTerms);
                    
                }
                

            }else{

                if (!$.isArray(allTerms) && !window.hWin.HEURIST4.util.isempty(allTerms)) {
                    //is it CS string - convert to array
                    allTerms = allTerms.split(',');
                }

                if(window.hWin.HEURIST4.util.isArrayNotEmpty(allTerms)){
                    if(window.hWin.HEURIST4.util.isnull(allTerms[0]['key'])){
                        //plain array
                        var idx, options = [];
                        for (idx=0; idx<allTerms.length; idx++){
                            options.push({key:allTerms[idx], title:allTerms[idx]});
                        }
                        allTerms = options;
                    }
                    //add empty value as a first option
                    //allTerms.unshift({key:'', title:''});
                    
                    //array of key:title objects
                    //if($input==null) $input = $('<select>').uniqueId();
                    var selObj = window.hWin.HEURIST4.ui.createSelector($input.get(0), allTerms);
                    window.hWin.HEURIST4.ui.initHSelect(selObj, this.options.useHtmlSelect);
                }
            }
            if(!window.hWin.HEURIST4.util.isnull(value)){
                
                if($($input).attr('radiogroup')){
                    $($input).find('input[value="'+value+'"]').attr('checked', true);
                }else {
                    $($input).val(value); 
                }
            }  
            if($($input).hSelect("instance")!=undefined){
                           $($input).hSelect("refresh"); 
            }

        }else{ //this is usual enumeration from defTerms

            var headerTerms = '';
            if(this.options.dtID==window.hWin.HAPI4.sysinfo['dbconst']['DT_RELATION_TYPE']){ //specific behaviour - show all
                allTerms = 0;
            }else{
                allTerms = this.f('rst_FilteredJsonTermIDTree');        
                //headerTerms - disabled terms
                headerTerms = this.f('rst_TermIDTreeNonSelectableIDs') || this.f('dty_TermIDTreeNonSelectableIDs');
            }

            //if($input==null) $input = $('<select>').uniqueId();
            
            //this.options.useHtmlSelect native select produce double space for option  Chrome touch screen
            
            //vocabulary
            $input = window.hWin.HEURIST4.ui.createTermSelectExt2($input.get(0),
                {datatype:this.detailType, termIDTree:allTerms, headerTermIDsList:headerTerms,
                    defaultTermID:value, topOptions:true, supressTermCode:true, useHtmlSelect:this.options.useHtmlSelect});
        
            var opts = $input.find('option');      
            if(opts.length==0 || (opts.length==1 && $(opts[0]).text()=='')){
               $input.hSelect('widget').html('<span style="padding: 0.1em 2.1em 0.2em 0.2em">no terms defined, please add terms</span><span class="ui-selectmenu-icon ui-icon ui-icon-triangle-1-e"></span>'); 
            }
            
            //show error message on init                    
            //value is not allowed
            if(!window.hWin.HEURIST4.util.isnull(value) && $input.val()!=value){
                
                var terms = window.hWin.HEURIST4.terms;
                var termLookup = terms.termsByDomainLookup[this.detailType];
                var sMsg = '';
                if(window.hWin.HEURIST4.util.isnull(termLookup[value])){
                    sMsg = 'The term code '+value+' recorded for this field is not recognised. Please select a term from the dropdown.';
                }else{
                    sMsg = 'The term "'+termLookup[value][terms.fieldNamesToIndex['trm_Label']]+'" (code '+value+') is not valid for this field. '
                    +'Please select from dropdown or modify field definition to include this term';
                }
                this.error_message.text(sMsg).show();
            }    
            
        }
        
        return $input;
    },
    
    //
    // internal - assign display value for specific input element
    //
    _clearValue: function(input_id, value, display_value){

        var that = this;
        $.each(this.inputs, function(idx, item){

            var $input = $(item);
            if($input.attr('id')==input_id){
                if(that.newvalues[input_id]){
                    that.newvalues[input_id] = '';
                }
                
                if(that.detailType=='file'){
                    that.input_cell.find('img.image_input').prop('src','');
                }else if(that.detailType=='resource'){
                    
                    $input.parent().find('.sel_link').hide();
                    $input.parent().find('.sel_link2').show();
                    $input.empty();
                    $input.hide();
                    
                }else if(that.detailType=='relmarker'){    
                    this.element.find('.rel_link').show();
                }else{
                    $input.val( display_value?display_value :value);    
                    
                    if(that.detailType=='enum' || that.detailType=='relationtype'){    
                        //selectmenu
                        if($($input).hSelect("instance")!=undefined){
                           $($input).hSelect("refresh"); 
                        }
                    }
                }
                if(that.detailType=='date' || that.detailType=='file'){
                    $input.change();
                }else{
                    that._onChange();
                }
                return;
            }

        });

    },

    //
    // recreate input elements and assign values
    //
    setValue: function(values){

        //clear ALL previous inputs
        this.input_cell.find('.input-div').remove();
        this.inputs = [];
        this.newvalues = {};
        
        if(!$.isArray(values)) values = [values];

        var isReadOnly = (this.options.readonly || this.f('rst_Display')=='readonly');

        var i;
        for (i=0; i<values.length; i++){
            if(isReadOnly){
                this._addReadOnlyContent(values[i]);
            }else{
                var inpt_id = this._addInput(values[i]);
            }
        }
        if (isReadOnly) {
            this.options.values = values;
        }

        var repeatable = (Number(this.f('rst_MaxValues')) != 1);
        if(values.length>1 && !repeatable){
            this.error_message.text('Repeated value for a single value field - please correct').show();
        }
        
        this._setAutoWidth();
    },
    
    //
    // get value for particular input element
    //  input_id - id or element itself
    //
    _getValue: function(input_id){

        if(this.detailType=="relmarker") return null;
        
        var res = null;
        var $input = $(input_id);

        if(!(this.detailType=="resource" || this.detailType=='file' 
            || this.detailType=='date' || this.detailType=='geo'))
        {
            if($input.attr('radiogroup')>0){
                res = $input.find('input:checked').val();
            }else{
                res = $input.val();    
            }
            
            if(!window.hWin.HEURIST4.util.isnull(res) && res!=''){
                res = res.trim();
            }
        }else {
            res = this.newvalues[$input.attr('id')];
        }

        return res;
    },

    
    //
    //
    //
    getConfigMode: function(){
        return this.configMode;
    },
    
    //
    //restore original order of repeatable elements
    //    
    _restoreOrder: function(){
        
        this.btn_cancel_reorder.hide();
        
        if(this.options.readonly || this.f('rst_Display')=='readonly') return;
        var idx, ele_after = this.error_message;
        for (idx in this.inputs) {
            var ele = this.inputs[idx].parents('.input-div');
            ele.insertAfter(ele_after);
            ele_after = ele;
        }
    },
    
    //
    // get all values (order is respected)
    //
    getValues: function(){

        if(this.options.readonly || this.f('rst_Display')=='readonly'){
            return this.options.values;
        }else{
            var idx;
            var ress = {};
            var ress2 = [];
            
            for (idx in this.inputs) {
                var res = this._getValue(this.inputs[idx]);
                if(!window.hWin.HEURIST4.util.isempty( res )){ 
                    
                    var ele = this.inputs[idx].parents('.input-div');
                    var k = ele.index();
                    
                    ress[k] = res;
                    //ress2.push(res);
                }
            }
            
            ress2 = [];
            for(idx in ress){
                ress2.push(ress[idx]);
            }
            if(ress2.length==0) ress2 = [''];//at least one empty value

            return ress2;
        }

    },

    
    //
    //
    //
    setDisabled: function(is_disabled){
        //return;
        if(!(this.options.readonly || this.f('rst_Display')=='readonly')){
            var idx;
            for (idx in this.inputs) {
                if(!this.isFileForRecord) {  //this.detailType=='file'
                    var input_id = this.inputs[idx];
                    var $input = $(input_id);
                    window.hWin.HEURIST4.util.setDisabled($input, is_disabled);
                }
            }
            this.is_disabled = is_disabled;
        }

    },
    
    //
    //
    //
    isChanged: function(value){

        if(value===true){
            this.options.values = [''];
            return true;
        }else{
        
            if(this.options.readonly || this.f('rst_Display')=='readonly'){
                return false;
            }else{
                if(this.options.values.length!=this.inputs.length){
                    return true;
                }
                
                var idx;
                for (idx in this.inputs) {
                    var res = this._getValue(this.inputs[idx]);
                    
                    //both original and current values are not empty
                    if (!(window.hWin.HEURIST4.util.isempty(this.options.values[idx]) && window.hWin.HEURIST4.util.isempty(res))){
                        if (this.options.values[idx]!=res){
                                return true;
                        }
                    }
                }
            }
        
            return false;        
        
        }
    },
    
    //
    // returns array of input elements
    //
    getInputs: function(){
        return this.inputs;
    },

    validate: function(){

        if (this.options.dtFields.rst_Display=='hidden' ||
        this.options.dtFields.rst_Display=='readonly') return true;
        
        var req_type = this.f('rst_RequirementType');
        var max_length = this.f('dty_Size');
        var data_type = this.f('dty_Type');
        var errorMessage = '';

        if(req_type=='required'){
            
            if(data_type=='relmarker'){
                    if(this.element.find('.link-div').length==0){
                        $(this.inputs[0]).addClass( "ui-state-error" );
                        //add error message
                        errorMessage = 'Define a relationship. It is required.';
                    }
            }else{
                var ress = this.getValues();

                if(ress.length==0 || window.hWin.HEURIST4.util.isempty(ress[0]) || 
                    ($.isPlainObject(ress[0]) &&  $.isEmptyObject(ress[0])) || 
                    ($.type(ress[0])=='string' && ress[0].trim()=='')) {
                    
                    
                    if( data_type=='file' && !this.isFileForRecord && this.entity_image_already_uploaded){
                        //special case for entity image
                        
                    }else{
                    
                        //error highlight
                        $(this.inputs[0]).addClass( "ui-state-error" );
                        //add error message
                        errorMessage = 'Field is required';
                    }

                }else if((data_type=='freetext' || data_type=='url' || data_type=='blocktext') && ress[0].length<4){
                    //errorMessage = 'Field is required';
                }
            }
        }
        //verify max alowed size
        if(max_length>0 &&
            (data_type=='freetext' || data_type=='url' || data_type=='blocktext')){

            var idx;
            for (idx in this.inputs) {
                var res = this._getValue(this.inputs[idx]);
                if(!window.hWin.HEURIST4.util.isempty( res ) && res.length>max_length){
                    //error highlight
                    $(this.inputs[idx]).addClass( "ui-state-error" );
                    //add error message
                    errorMessage = 'Value exceeds max length: '+max_length;
                }
            }
        }
        if(data_type=='integer' || this.detailType=='year'){
            //@todo validate 
            
        }else if(data_type=='float'){
            
            
        }
        

        if(errorMessage!=''){
            this.error_message.text(errorMessage);
            this.error_message.show();
        }else{
            this.error_message.hide();
            $(this.inputs).removeClass('ui-state-error');
        }

        return (errorMessage=='');
    },

    
    focus: function(){
        if(this.inputs && this.inputs.length>0){
            $(this.inputs[0]).focus();   
            return true;
        }else{
            return false;
        }
    },

    //
    //
    //
    _addReadOnlyContent: function(value, idx) {

        var disp_value ='';
        

        var $inputdiv = $( "<div>" ).addClass('input-div')
                .css({'font-weight':'bold'})
                .insertBefore(this.input_prompt);

        var dwidth = this.f('rst_DisplayWidth');
        if (parseFloat( dwidth ) > 0 
            &&  this.detailType!='boolean' && this.detailType!='date' && this.detailType!='resource' ) {
             $inputdiv.css('max-width', Math.round(2 + Math.min(80, Number(dwidth))) + "ex");
        }
                
        if($.isArray(value)){

            disp_value = value[1]; //record title, relation description, filename, human readable date and geo

        }else if(this.detailType=="enum" || this.detailType=="relationtype"){

            disp_value = window.hWin.HEURIST4.ui.getTermValue(value, true);

            if(window.hWin.HEURIST4.util.isempty(value)) {
                disp_value = 'term missed. id '+termID
            }
        } else if(this.detailType=='file'){

            $inputdiv.addClass('truncate').css({'max-width':'400px'});
            
            this._findAndAssignTitle($inputdiv, value);
            return;

        } else if(this.detailType=="resource"){

            $inputdiv.html("....resource "+value);

            this._findAndAssignTitle($inputdiv, value);
            return;

        } else if(this.detailType=="relmarker"){  //combination of enum and resource

            disp_value = "@todo relation "+value;

            //@todo NEW datatypes
        } else if(this.detailType=="geo"){

            /*if(detailType=="query")
            if(detailType=="color")
            if(detailType=="bool")
            if(detailType=="password")*/

            disp_value = "@todo geo "+value;


        } else if(this.detailType=="url"){

            var def_value = this.f('rst_DefaultValue');
            if(window.hWin.HEURIST4.util.isempty(value)) value = def_value;
            
            if(!window.hWin.HEURIST4.util.isempty(value) &&
               !(value.indexOf('http://')==0 || value.indexOf('https://')==0)){
                value = 'http://'+ value;
            }
            disp_value = '<a href="'+value+'" target="_blank" title="'+value+'">'+value+'</a>';
            
            $inputdiv.addClass('truncate').css({'max-width':'400px'});
        }else{
            disp_value = value;
            
            $inputdiv.addClass('truncate').css({'max-width':'400px'});
        }

        if(this.detailType=="blocktext"){
            this.input_cell.css({'padding-top':'0.4em'});
        }

        $inputdiv.html(disp_value);

        /*
        if(this.detailType=="url"){

            $btn_extlink = $( '<span>', {title: 'Open URL in new window'})
                .addClass('smallicon ui-icon ui-icon-extlink')
                .appendTo( $inputdiv.find('div') );
        
            this._on( $btn_extlink, { click: function(){ window.open(value, '_blank') }} );
        
        }
        */   
    }


});
