$(function () {
  console.log('foo');
  $("#namefind").autocomplete({
    source: 'lookup.php',
    minLength: 2,
    delay: 100,
    select: function (event, ui) {
      window.location = './?name=' + encodeURIComponent(ui.item.name);
    }
  })
    .autocomplete("instance")._renderItem = function (ul, item) {
      return $("<li>")
        //      .attr( "style", "background: url('" + item.thumbnail + "'); background-size: contain; background-repeat: no-repeat; background-position: right;")
        //          .append(" <a>" + item.label + "<br>" + item.id + "<br>" + item.class + "</a>" )
        .append(" <b>" + item.name + "</b>")
        .append(" <br>" + item["name:etymology:wikidata"] + "</span>")
        .appendTo(ul);
    }
});
