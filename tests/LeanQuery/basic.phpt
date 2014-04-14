<?php

use LeanMapper\Connection;
use LeanQuery\QueryHelper;
use Tester\Assert;
use Tester\Dumper;

require_once __DIR__ . '/../bootstrap.php';

$queryHelper = new QueryHelper($mapper);

$sql = (string) $connection->select($queryHelper->formatSelect(Author::class) + $queryHelper->formatSelect(Book::class))
		->from($queryHelper->formatFrom(Author::class))
		->join($queryHelper->formatJoin(Book::class))->on($queryHelper->formatOn(Book::class, 'author'))
		->where('%sql != %s', $queryHelper->formatColumn(Book::class, 'name'), 'The Pragmatic Programmer')
		->where('LENGTH(%sql) > %i', $queryHelper->formatColumn(Book::class, 'name'), 13);

$expected =
		"SELECT [author].[id] AS [author_id], [author].[name] AS [author_name], [author].[web] AS [author_web], " .
		"[book].[id] AS [book_id], [book].[author_id] AS [book_author_id], [book].[reviewer_id] AS [book_reviewer_id], [book].[name] AS [book_name], " .
		"[book].[pubdate] AS [book_pubdate], [book].[description] AS [book_description], [book].[website] AS [book_website], [book].[available] AS [book_available] " .
		"FROM [author] AS [author] " .
		"JOIN [book] ON [book].[author_id] = [author].[id] " .
		"WHERE [book_name] != 'The Pragmatic Programmer' AND LENGTH([book_name]) > 13";

Assert::equal($expected, $sql);

//////////

$bookAlias = 'knizka';
$authorAlias = 'spisovatel';
$authorPrefix = 's';
$bookPrefix = 'b';

$sql = (string) $connection->select($queryHelper->formatSelect(Author::class, $authorAlias, $authorPrefix) + $queryHelper->formatSelect(Book::class, $bookAlias, $bookPrefix))
		->from($queryHelper->formatFrom(Author::class, $authorAlias))
		->join($queryHelper->formatJoin(Book::class, $bookAlias))->on($queryHelper->formatOn(Book::class, 'author', $bookAlias, $authorAlias))
		->where('%sql != %s', $queryHelper->formatColumn(Book::class, 'name', null, $bookPrefix), 'The Pragmatic Programmer')
		->where('LENGTH(%sql) > %i', $queryHelper->formatColumn(Book::class, 'name', null, $bookPrefix), 13);

$expected =
		"SELECT [spisovatel].[id] AS [s_id], [spisovatel].[name] AS [s_name], [spisovatel].[web] AS [s_web], " .
		"[knizka].[id] AS [b_id], [knizka].[author_id] AS [b_author_id], [knizka].[reviewer_id] AS [b_reviewer_id], [knizka].[name] AS [b_name], " .
		"[knizka].[pubdate] AS [b_pubdate], [knizka].[description] AS [b_description], [knizka].[website] AS [b_website], [knizka].[available] AS [b_available] " .
		"FROM [author] AS [spisovatel] " .
		"JOIN [book] [knizka] ON [knizka].[author_id] = [spisovatel].[id] " .
		"WHERE [b_name] != 'The Pragmatic Programmer' AND LENGTH([b_name]) > 13";

Assert::equal($expected, $sql);
