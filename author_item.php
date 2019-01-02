<?PHP

// error_reporting(E_ERROR|E_CORE_ERROR|E_COMPILE_ERROR); // E_ALL|
error_reporting(E_ALL);
ini_set('display_errors', 'On');
ini_set('memory_limit','1500M');
set_time_limit ( 60 * 10 ) ; // Seconds

require_once ( __DIR__ . '/magnustools/common.php' ) ;
require_once ( __DIR__ . '/magnustools/wikidata.php' ) ;
require_once ( __DIR__ . '/lib/article_model.php' ) ;
require_once ( __DIR__ . '/lib/cluster.php' ) ;
require_once ( __DIR__ . '/lib/clustering.php' ) ;
require_once ( __DIR__ . '/lib/qs_commands.php' ) ;

function print_footer () {
	print "<hr/><a href='https://github.com/arthurpsmith/author-disambiguator/issues' target='_blank'>Feedback</a><br/><a href='https://github.com/arthurpsmith/author-disambiguator/'>Source and documentation (at github)</a><br/>" ;
	print get_common_footer() ;
}

$action = get_request ( 'action' , '' ) ;
$author_qid = get_request( 'id', '' ) ;

print get_common_header ( '' , 'Author Disambiguator' ) ;

print "<form method='get' class='form form-inline'>
Author Wikidata ID: 
<input name='id' value='" . escape_attribute($author_qid) . "' type='text' placeholder='Qxxxxx' />
<input type='submit' class='btn btn-primary' name='doit' value='Get author data' />
</form>" ;

if ( $author_qid == '' ) {
	print_footer() ;
	exit ( 0 ) ;
}

$wil = new WikidataItemList ;

$delete_statements = array() ;
if ( $action == 'remove' ) {
	print "<form method='post' class='form' action='https://tools.wmflabs.org/quickstatements/api.php'>" ;
	print "<input type='hidden' name='action' value='import' />" ;
	print "<input type='hidden' name='temporary' value='1' />" ;
	print "<input type='hidden' name='openpage' value='1' />" ;

	$author_match = trim ( get_request ( 'author_match' , '' ) ) ;
	$new_author_q = trim ( get_request ( 'new_author_q' , '' ) ) ;

	$papers = get_request ( 'papers' , array() ) ;

	$to_load = array() ;
	$to_load[] = $author_qid ;
	foreach ( $papers AS $q ) $to_load[] = $q ;
	$wil->loadItems ( $to_load ) ;

	if ( $author_match == 'none' ) {
		$commands = revert_authors_qs_commands ( $wil, $papers, $author_qid ) ;
	} else {
		$commands = move_authors_qs_commands ( $wil, $papers, $author_qid, $new_author_q) ;
	}

	print "Quickstatements V1 commands for replacing author items on these papers:" ;
	print "<textarea name='data' rows=20>" . implode("\n",$commands) . "</textarea>" ;
	print "<input type='submit' class='btn btn-primary' name='qs' value='Send to Quickstatements' />" ;
	print "</form>" ;
	
	exit ( 0 ) ;
}


$sparql = "SELECT ?q { ?q wdt:P50 wd:$author_qid }" ;
$items_papers = getSPARQLitems ( $sparql ) ;


// Load items
$to_load = array() ;
$to_load[] = $author_qid ;
foreach ( $items_papers AS $q ) $to_load[] = $q ;
$wil->loadItems ( $to_load ) ;

$to_load = array() ;
$article_items = array();
foreach ( $items_papers AS $q ) {
	$i = $wil->getItem ( $q ) ;
	if ( !isset($i) ) continue ;

	$article = new WikidataArticleEntry( $i ) ;
	$article_items[] = $article ;

	foreach ( $article->authors AS $auth ) $to_load[] = $auth ;
	foreach ( $article->published_in AS $pub ) $to_load[] = $pub ;
	foreach ( $article->topics AS $topic ) $to_load[] = $topic ;
}
$wil->loadItems ( $to_load ) ;

usort( $article_items, 'WikidataArticleEntry::dateCompare' ) ;

$author_item = $wil->getItem ( $author_qid ) ;
if ( !isset($author_item) )  {
	print "<h2>Warning: $author_qid not found!</h2>" ;
	print_footer() ;
	exit ( 0 ) ;
}
print "<h2>" . $author_item->getLabel() . "</h2>" ;
print "<div>" ;
print "<a target='_blank' href='https://www.wikidata.org/wiki/$author_qid'>Wikidata Item</a>" ;
print ' | ' ;
print "<a target='_blank' href='https://tools.wmflabs.org/scholia/author/$author_qid'>Scholia Profile</a>" ;
print ' | ' ;
print "<a target='_blank' href='https://tools.wmflabs.org/reasonator/?q=$author_qid'>Reasonator</a>" ;
print '</div>' ;

print "<form method='post' class='form' target='_blank' action='?'>
<input type='hidden' name='action' value='remove' />
<input type='hidden' name='id' value='$author_qid' />" ;

// Publications
$name_counter = array() ;
print "<h2>Listed Publications</h2>" ;
print "<p>" . count($article_items) . " publications found</p>" ;

print "<div class='group'>" ;
?>
<div>
<a href='#' onclick='$($(this).parents("div.group")).find("input[type=checkbox]").prop("checked",true);return false'>Check all</a> | 
<a href='#' onclick='$($(this).parents("div.group")).find("input[type=checkbox]").prop("checked",false);return false'>Uncheck all</a>
</div>
<?PHP
print "<table class='table table-striped table-condensed'>" ;
print "<tbody>" ;
print "<tr><th></th><th>Title</th>" ;
print "<th>Author Name Strings</th><th>Identified Authors</th>" ;
print "<th>Published In</th><th>Identifier(s)</th>" ;
print "<th>Topic</th><th>Published Date</th></tr>" ;
foreach ( $article_items AS $article ) {
	$q = $article->q ;

	$out = array() ;
	foreach ( $article->author_names AS $num => $a ) {
		$out[] = "[$num]<a href='index.php?name=" . urlencode($a) . "'>$a</a>" ;
			$name_counter[$a] = isset($name_counter[$a]) ? $name_counter[$a]+1 : 1 ;
	}
	$author_string_list = implode ( ', ' , $out ) ;
		
	$q_authors = array() ;
	foreach ( $article->authors AS $num => $qt ) {
		$i2 = $wil->getItem ( $qt ) ;
		$label = $i2->getLabel() ;
		if ( $qt == $author_qid ) {
			$q_authors[] = "[$num]<b>$label</b>" ;
		} else {
			$q_authors[] = "[$num]<a href='?id=" . $i2->getQ() . "' style='color:green'>$label</a>" ;
		}
	}
	$author_entity_list = implode ( ', ' , $q_authors ) ;

	$published_in = array() ;
	foreach ( $article->published_in AS $qt ) {
		$i2 = $wil->getItem ( $qt ) ;
		if ( isset($i2) ) $published_in[] = $i2->getLabel() ;
	}
	$published_in_list = implode ( ', ', $published_in ) ;
	
	print "<tr>" ;
	print "<td><input type='checkbox' name='papers[$q]' value='$q'/></td>" ;
	print "<td style='width:20%;font-size:10pt'><a href='//www.wikidata.org/wiki/$q' target='_blank'>" . $article->title . "</a></td>" ;
	print "<td style='width:30%;font-size:9pt'>$author_string_list</td>" ;
	print "<td style='width:30%;font-size:9pt'>$author_entity_list</td>" ;
	print "<td style='font-size:9pt'>$published_in_list</td>" ;
	print "<td style='font-size:9pt'>" ;
	if ( $article->doi != '' ) {
		print "DOI: <a target='_blank' href='https://doi.org//$article->doi'>$article->doi</a><br/>" ;
	}
	if ( $article->pmid != '' ) {
		print "PubMed: <a target='_blank' href='https://www.ncbi.nlm.nih.gov/pubmed/?term=$article->pmid'>$article->pmid</a>" ;
	}
	print "</td>" ;
	print "<td style='font-size:9pt'>" ;
	if ( count($article->topics) > 0 ) {
		$topics = [] ;
		foreach ( $article->topics AS $qt ) {
			$i2 = $wil->getItem($qt) ;
			if ( !isset($i2) ) continue ;
			$topics[] = "<a href='https://www.wikidata.org/wiki/" . $i2->getQ() . "' target='_blank' style='color:red'>" . $i2->getLabel() . "</a>" ;
		}
		print implode ( '; ' , $topics ) ;
	}
	print "</td>" ;
	print "<td style='font-size:9pt'>" ;
	print $article->formattedPublicationDate () ;
	print "</td>" ;
	print "</tr>" ;
}
print "</tbody></table></div>" ;

print "<div><input type='radio' name='author_match' value='manual' /><span style='display:inline-block; width:200px'><input type='text' name='new_author_q' placeholder='Qxxx' /></span>Correct Q number of author item for selected works.</div>" ;
print "<div><input type='radio' name='author_match' value='none' checked /> NO replacement author: revert to author name strings</div>" ;
print "<div style='margin:20px'><input type='submit' name='doit' value='Quickstatements to REMOVE selected works from this author' class='btn btn-primary' /></div>" ;
print "</form>";

arsort ( $name_counter , SORT_NUMERIC ) ;
print "<h2>Common names in these papers</h2>" ;
print "<ul>" ;
foreach ( $name_counter AS $a => $cnt ) {
	if ( $cnt == 1 ) break ;
	print "<li><a href='index.php?name=" . urlencode($a) . "'>$a</a> ($cnt&times;)</li>" ;
}
print "</ul>" ;

print_footer() ;

?>
