# Knowledge Base

## Running the knowledge base

### On a PHP webserver

The current knowledge base is a CGI script, written in PHP, and thus
meant to be run under a web server capable of running PHP.

One such server is the built-in server of the PHP interpreter, and
thus one way to run the server is to run, e.g.,

    php -S localhost:9999

in the directory with the kb.php program. (This example starts a
server listening on port 9999.)

The knowledge base can then be queried with URLs such as this:

    http://localhost:9999/kb.php?action=definitions&term=gdpr

### As a Docker container

You can build the current repository as a docker container using the following command, once in the directory.

    docker build -t $image_name ./ 
Where $image_name could for example be "tpz/knowledge-base".

After that, you can run the image like so:

    docker run -p $port:80 $image_name

Where $port is the port you want to bind this container on your machine (make sure it's free), for example: "8080".

The knowledge base should then be available on:

    http://localhost:$port/kb.php?action=definitions&term=gdpr

## API

The currently implemented queries are of the following forms:

<dl>
<dt>?action=search&amp;words=<var>keywords</var></dt>

<dd>This returns a JSON file with definitions for the keywords (if
available) and links to online articles related to the keywords. The
keywords must separated by spaces, commas or semicolons. A term
consisting of two or more words can be enclosed in double quotes. (The
punctuation must be %-escaped to make a valid URL. The language
preferences expressed in the ‘Accept-Language’ header of the HTTP
request determine what language the responses are in. In the absence
of an ‘Accept-Language’ header, the response is in English.</dd>

<dt>?action=definition&amp;term=<var>term</var></dt>

<dd>This returns a JSON file with the definition (or definitions, if
there are several) of the term. As above, the ‘Accept-Language’ header
determines the language of the response.</dd>

<dt>?action=gdpr&amp;article=<var>number</var></dt>

<dd>This returns a JSON file with the text of the given article of the
GDPR. The number must be of the form ‘<var>number</var>’ or
‘<var>number</var>(<var>number</var>)’ or
‘<var>number</var>(<var>number</var>)(<var>letter</var>)’, e.g., ‘1’
(the whole of article 1), ‘2(1)’ (clause 1 of article 2), or ‘3(2)(b)’
(sub-clause b of clause 2 of article 3). As above, the
‘Accept-Language’ header determines the language of the response.</dd>

<dt>?action=dpa&amp;country=<var>XY</var></dt>
<dt>?action=dpa&amp;name=<var>text</var></dt>
<dt>?action=dpa</dt>

<dd>This returns a JSON file with a list of Data Protection
Agencies. If a contry code
<var>XY</var> (ISO-3166 two-letter code) is given, the DPAs for that country
are returned (usually just one). If a <var>text</var> is given, the DPAs
whose names contain that text are returned. If neither a country nor a
name are given, all DPAs are returned. For each DPA, the result
contains the country (two-letter ISO code), the name, the postal
address, a phone number, a fax number, an email address and a web
address. The fax number and the email address may be NULL. The
‘Accept-Language’ header determines the language of the response, but
if no information in that language is available, the result is in
English. (Except for the third form of the query, without language
code or name, which always returns results in English.)

<dt>?action=articles&amp;words=<var>text</var>

<dd>This returns a JSON file with a list of URLs of documents whose
abstract, keywords,
title or authors include the words from the given <var>text</var>. The
<var>text</var> should currently use the syntax of the FTS5 full-text
search engine for SQLite.  The result is an array of records, where
each record contains the title, the abstract, the keywords, the
authors, the kind of document (article or slide set) and the URL of
the document.

<dt>?action=dpv&amp;term=<var>term</var></dt>

<dd>This returns a JSON file with the definition of a term from the
[DPV vocabulary](https://w3c.github.io/dpv/dpv/). The term can be a
full URL (e.g., ‘https://w3id.org/dpv#Transfer’) or just the term
relative to the vocabulary (e.g., ‘Transfer’). As above, the
‘Accept-Language’ header determines the language of the response. (At
the moment, labels, notes and human-readable definitions are only
available in English.)</dd>

</dl>

