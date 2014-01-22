/**
*  Button - logo that opens heurist-about dialogue
*/
$.widget( "heurist.about", {

  // default options
  options: {
  },

  // the constructor
  _create: function() {

    var that = this;

    this.element
      // prevent double click to select text
      .disableSelection();

    this.div_logo = $( "<div>")
            .addClass('logo')
            .css('width','150px')
            .css('display','inline-block')
            .appendTo( this.element )
            .button();

    // bind click events
    this._on( this.div_logo, {
      click: function(){$( "#heurist-about" ).dialog("open");}
    });

    this.div_dbname = $( "<div>")
            .css({'font-size':'2em', 'font-style':'italic', 'padding-left':'15px', 'display':'inline-block',  'vertical-align': 'middle'})
            .html(top.HAPI.database)
            .appendTo( this.element );
    
    this._refresh();

  }, //end _create

  /* private function */
  _refresh: function(){
  },

  // events bound via _on are removed automatically
  // revert other modifications here
  _destroy: function() {
    // remove generated elements
    this.div_logo.remove();
  }

});
