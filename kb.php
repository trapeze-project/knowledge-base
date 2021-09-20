<?php

# Database description.

define('DSN', 'sqlite:./database.db');

# Error messages. These functions call gettext() to return a localized message.

function ERR_USAGE()
{
  return _('<html lang=en>
<title>Missing or unknown ‘action’ parameter</title>
<h1>Missing or unknown ‘action’ parameter</h1>
<p>The ‘action’ parameter must be present and must be one of
‘search’, ‘definitions’, ‘gdpr’ or ‘status’.
');
}

function ERR_NOT_IMPLEMENTED()
{
  return _('<html lang=en>
<title>Not yet implemented</title>
<p>Not yet implemented.
');
}

function ERR_MISSING_WORDS()
{
  return _('<html lang=en>
<title>Missing ‘words’ parameter</title>
<h1>Missing ‘words’ parameter</h1>
<p>When the ‘action’ parameter is ‘search’,
the ‘words’ parameter is required.
');
}

function ERR_MISSING_TERM()
{
  return _('<html lang=en>
<title>Missing ‘term’ parameter</title>
<h1>Missing ‘term’ parameter</h1>
<p>When the ‘action’ parameter is ‘definitions’,
the ‘term’ parameter is required.
');
}

function ERR_MISSING_ARTICLE()
{
  return _('<html lang=en>
<title>Missing ‘article’ parameter</title>
<h1>Missing ‘article’ parameter</h1>
<p>When the ‘action’ parameter is ‘gdpr’,
the ‘article’ parameter is required.
');
}

function ERR_NO_DEFINITION()
{
  return _('<html lang=en>
<title>No definition found</title>
<p>No definition found.
');
}

function ERR_NO_SUCH_ARTICLE()
{
  return _('<title>No such article</title>
<h1>No such article</h1>
<p>The requested article or clause does not exist.
');
}

function ERR_DATABASE()
{
  return _('<html lang=en>
<title>Server error: Database not available</title>
<h1>Server error: Database not available</h1>
<p>An error occurred when trying to open the database.
');
}

function ERR_NO_SUCH_DPA()
{
  return _('<title>No such DPA</title>
<h1>No such DPA</h1>
<p>No Data Protection Agency exists for the selected country or with the given name.
');
}


# get_languages -- parse an accept-language string into a sorted array
function get_languages(string $lang)
{
  $langs = [];                  # The resulting list of languages
  $weights = [];                # Array of quality factors, used for sorting
  foreach (explode(',', $lang) as $s) {
    if (preg_match('/^\s*([a-z-]+)\s*(?:;\s*q=([0-9.]+))?/i', $s, $matches)) {
      # Get the q factor (or 1.0 if not set), and the language code.
      $weight = 0 + ($matches[2] ?? 1.0);
      $code = strtolower($matches[1]);
      # Insert the language & weight at the right places in the sorted arrays.
      for ($i = 0; $i < count($weights) && $weights[$i] >= $weight; $i++);
      array_splice($weights, $i, 0, $weight);
      array_splice($langs, $i, 0, $code);
    } else {
      # TODO: Report a syntax error in the string?
    }
  }
  return $langs;
}


# find_definitions -- find definitions of term, in the preferred language
function find_definitions(object $db, array $langs, string $term)
{
  # TODO: handle language variants ('fr' vs 'fr-be' vs 'fr-fr')
  $result = [];
  $stmt = $db->prepare('SELECT language, term, definition FROM definitions
    WHERE lower(:t) = lower(term) AND :l = language');
  for ($i = 0; $result == [] && $i < count($langs); $i++) {
    $stmt->execute([':t' => $term, ':l' => $langs[$i]]);
    while (($row = $stmt->fetch()))
      $result[] = [
        '@context' => ['@language' => $row['language']],
        'term' => $row['term'],
        'definition' => $row['definition']];
  }
  return $result;
}


# search_articles -- return array of online articles relevant to $words
function search_articles(object $db, array $langs, array $words)
{
  return [];                    # Not yet implemented
}


# search -- return various kinds of information related to given keywords
function search(object $db, array $langs)
{
  $words = $_REQUEST['words'];
  if (!isset($words)) return array(400, ERR_MISSING_WORDS());

  $info = ['definitions' => [], 'articles' => []];

  # A "word" is delimited by white space, or it is anything between quotes (")
  $words = preg_split('/[,;\s]*"([^"]+)"[,;\s]*|[,;\s]+/', $words, 0,
    PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

  # See if we have definitions for the words.
  foreach ($words as $word)
    array_push($info['definitions'], ...find_definitions($db, $langs, $word));

  # See if there are online articles relevant to the words.
  array_push($info['articles'], search_articles($db, $langs, $words));

  return array(200, $info);
}


# definitions -- return the definitions of a given term
function definitions(object $db, array $langs)
{
  $term = $_REQUEST['term'];
  if (!isset($term)) return array(400, ERR_MISSING_TERM());

  $defs = find_definitions($db, $langs, $term);
  return array(200, ['definitions' => $defs]);
}


# gdpr -- given a number ("30(1)(g)") return that article/clause from the GDPR
function gdpr(object $db, array $langs)
{
  # TODO: Also handle sections from the preamble.
  $results = [];

  $number = $_REQUEST['article'];
  if (!isset($number))
    return array(400, ERR_MISSING_ARTICLE());
  if (!preg_match('/^\s*(\d+)(?:\((\d+)\)(?:\((\w)\))?)?\s*$/', $number, $m))
    return array(400, ERR_INVALID_ARTICLE());

  # Create query based on whether articles, clauses or sub-clauses are desired.
  $s = 'SELECT language, article, clause, subclause, text FROM gdpr
    WHERE language = :l AND article = :a';
  if ($m[2]) $s .= ' AND clause = :c';
  if ($m[3]) $s .= ' AND subclause = :s';
  $s .= ' ORDER BY clause, subclause';
  $stmt = $db->prepare($s);

  # Try the preferred languages until one succeeds.
  for ($i = 0; $results == [] && $i < count($langs); $i++) {
    $stmt->bindValue(':l', $langs[$i], PDO::PARAM_STR);
    $stmt->bindValue(':a', $m[1], PDO::PARAM_INT);
    if ($m[2]) $stmt->bindValue(':c', $m[2], PDO::PARAM_INT);
    if ($m[3]) $stmt->bindValue(':s', $m[3], PDO::PARAM_STR);
    $stmt->execute();
    while (($row = $stmt->fetch())) {
      $n = $row['article'];
      if ($row['clause']) $n .= '(' . $row['clause'] . ')';
      if ($row['subclause']) $n .= '(' . $row['subclause'] . ')';
      $results[] = [
        '@context' => ['@language' => $row['language']],
        'n' => $n,
        'text' => $row['text']];
    }
  }

  if ($results == [])
    return array(400, ERR_NO_SUCH_ARTICLE());
  else
    return array(200, $results);
}


# dpa -- return the address of a DPA, by country code or partial name
function dpa(object $db, array $langs)
{
  $results = [];

  $country = $_REQUEST['country'];
  $partialname = $_REQUEST['name'];

  # Create a SQL query.
  # Note that parameters are used twice in a query. This only works
  # when PDO::ATTR_EMULATE_PREPARES is set on the database handle.
  if (isset($country)) {
    $stmt = $db->prepare('SELECT language, country, name, address, tel, fax,
      email, url FROM dpa WHERE lower(country) = lower(:country)
      AND (language = :language OR (language = "en" AND
        NOT EXISTS (SELECT * FROM dpa WHERE lower(country) = lower(:country)
          AND language = :language)))');
  } else if (isset($partialname)) {
    $stmt = $db->prepare('SELECT language, country, name, address, tel, fax,
      email, url FROM dpa WHERE name LIKE :partialname
      AND (language = :language OR (language = "en" AND
        NOT EXISTS (SELECT * FROM dpa WHERE name LIKE :partialname
          AND language = :language)))');
  } else {                      # Return addresses in English of all DPAs
    $stmt = $db->prepare('SELECT language, country, name, address, tel, fax,
      email, url FROM dpa WHERE language = "en"');
  }

  # Try the preferred languages until one succeeds.
  $langs[] = 'en';              # Add English at the end
  error_log("Starting executing query", 0);
  for ($i = 0; $results == [] && $i < count($langs); $i++) {
    error_log("Trying with language $langs[$i]", 0);
    if (isset($country)) {
      $stmt->bindValue(':language', $langs[$i], PDO::PARAM_STR);
      $stmt->bindValue(':country', $country, PDO::PARAM_STR);
    } else if (isset($partialname)) {
      $stmt->bindValue(':language', $langs[$i], PDO::PARAM_STR);
      $stmt->bindValue(':partialname', "%$partialname%", PDO::PARAM_STR);
    }
    $res = $stmt->execute();
    if (!$res) {error_log("Execute failed", 0);}
    while (($row = $stmt->fetch())) {
      $results[] = [
        '@context' => ['@language' => $row['language'] ],
        'country' => $row['country'],
        'name' => $row['name'],
        'address' => $row['address'],
        'tel' => $row['tel'],
        'fax' => $row['fax'],
        'email' => $row['email'],
        'url' => $row['url'] ];
    }
  }

  if ($results == [])
    return array(400, ERR_NO_SUCH_DPA());
  else
    return array(200, $results);

}

# usage -- return an error message explaining how to use this service
function usage(array $langs)
{
  return array(400, ERR_USAGE());
}


# status -- return some information about the service
function status(?object $db, array $langs)
{
  return array(200, ['status' => [
    'langs' => $langs,
    'cwd' => getcwd()]]);
}


# main

# Check what languages the client prefers, default to English.
$langs = get_languages($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en');

# Set up localization of error messages according to the client's languages.
# If that fails (PHP was not compiled with libintl), make _() a no-op.
try {
  $domain = 'kb';
  bindtextdomain($domain, './locale'); # Find message files under this directory
  textdomain($domain);                 # Base name of translated message files
  bind_textdomain_codeset($domain, 'UTF-8');
  putenv('LANGUAGE=' . implode(':', $langs));
} catch (Throwable $e) {
  function _($s) {return $s;}
}

# Connect to our database, then dispatch the request to the appropriate handler.
try {
  $db = new PDO(DSN);
  $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
  switch ($_REQUEST['action']) {
    case 'search': list($status, $result) = search($db, $langs); break;
    case 'definitions': list($status, $result) = definitions($db, $langs);break;
    case 'gdpr': list($status, $result) = gdpr($db, $langs); break;
    case 'dpa': list($status, $result) = dpa($db, $langs); break;
    case 'status': list($status, $result) = status($db, $langs); break;
    default: list($status, $result) = usage($langs);
  }
} catch (PDOException $e) {
  $status = 500;
  $result = ERR_DATABASE();
}

# Return the result, either an error message in HTML or a bit of JSON.
if ($status != 200) {
  header($_SERVER['SERVER_PROTOCOL'] . ' ' . $status);
  header('Content-Type: text/html;charset=utf-8');
  echo $result;
} else {
  header($_SERVER['SERVER_PROTOCOL'] . ' 200 OK');
  header('Content-Type: application/json');
  echo json_encode($result, JSON_UNESCAPED_UNICODE);
}
