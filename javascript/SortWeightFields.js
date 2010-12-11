SortWeightField = Class.create();
SortWeightField.prototype = {
	initialize: function() {
		var $ = jQuery,
			tbody = $(this).closest('tbody')[0],
			e = $(this),
			TableHref = e.closest('div.TableListField').attr('href').replace(/\//g,"|"), // TODO: unit test against CTF or [better] patch CTF to provide reliable relation hint.s
			APIURL = 'SortWeight_Controller';  
		
		// initialize sorting
		if(!tbody._sortable)
		{
			tbody._sortable = true;
			$(tbody).sortable({
				helper: function(e, ui) { 
					ui.children().each(function(){ $(this).width($(this).width());});
					return ui;
				},
				update: function(e, ui){
					var e = $('td.SortWeight',ui.item).html('<img src="cms/images/network-save.gif" />'),
						above = $('td.SortWeight',$(ui.item).prev('tr')),
						below = $('td.SortWeight',$(ui.item).next('tr')),
						URL = APIURL + '/' + e.attr('rel') + '/NextTo/' + TableHref + '/';
					
					if(above.length > 0 && below.length > 0)
					{
						URL += (ui.originalPosition.top > ui.position.top) ?
							below.attr('rel') : above.attr('rel');
					}
					else
					{
						URL += (above.length > 0) ?
							above.attr('rel') : below.attr('rel');
					}
			
		
					$.getJSON(URL, function(json){
						e.closest('div.TableListField')[0].refresh();
					});
				}
			}).disableSelection();
		}
		
		e.attr('rel',e.html()).html('<div class="ui-icon ui-icon-arrowthick-2-n-s" title="Drag to Sort"></div><div class="ui-icon ui-icon-arrowthickstop-1-n" title="Move to Top"></div><div class="ui-icon ui-icon-arrowthickstop-1-s" title="Move to Bottom"></div>');
		
		// TODO: refactor using OO to get rid of redundancy
		$('div',e).click(function(){
			var e = $(this);
			console.log(e);
			if(e.is('.ui-icon-arrowthickstop-1-n'))
			{
				var e = e.parent().html('<img src="cms/images/network-save.gif" />'),
					URL = APIURL + '/' + e.attr('rel') + '/ToTop/' + TableHref;
				
				$.getJSON(URL, function(json){
					e.closest('div.TableListField')[0].refresh();
				});
			}
			else if(e.is('.ui-icon-arrowthickstop-1-s')) {
				var e = e.parent().html('<img src="cms/images/network-save.gif" />'),
				URL = APIURL + '/' + e.attr('rel') + '/ToBottom/' + TableHref;
			
				$.getJSON(URL, function(json){
					e.closest('div.TableListField')[0].refresh();
				});
			}
		}).css({float: 'left',marginLeft: '1.3em'});
		
		
		
	},
	onclick: function(){ return false; }
}
			


// make sure we register AFTER TableList is avail; so we can override onclicks
// TODO: improve this. Unable to unbind the behavior via jQuery -- revisit during SS 2.5/3.0 rewrite
function SortWeightInit(){
	if(typeof(ComplexTableField) == 'undefined')
		return setTimeout(SortWeightInit,555);
	SortWeightField.applyTo('table.data td.SortWeight');
};
SortWeightInit();



