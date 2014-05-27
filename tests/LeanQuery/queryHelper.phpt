<?php

use LeanMapper\Connection;
use LeanQuery\QueryHelper;
use Tester\Assert;
use Tester\Dumper;

require_once __DIR__ . '/../bootstrap.php';

$queryHelper = new QueryHelper($mapper);

$authorReflection = Author::getReflection($mapper);
$authorTable = $mapper->getTable(Author::class);

$bookReflection = Book::getReflection($mapper);
$bookTable = $mapper->getTable(Book::class);

$bookAlias = 'knizka';
$authorAlias = 'spisovatel';
$authorPrefix = 's';
$bookPrefix = 'b';

//////////

$sql = (string) $connection->select($queryHelper->formatSelect($authorReflection, $authorTable) + $queryHelper->formatSelect($bookReflection, $bookTable))
		->from($authorTable)
		->join($bookTable)->on('%n.%n = %n.%n', $bookTable, $bookReflection->getEntityProperty('author')->getColumn(), $authorTable, $authorReflection->getEntityProperty('id')->getColumn())
		->where('%n != %s', $queryHelper->formatColumn($bookReflection->getEntityProperty('name'), $bookTable), 'The Pragmatic Programmer')
		->where('LENGTH(%n) > %i', $queryHelper->formatColumn($bookReflection->getEntityProperty('name'), $bookTable), 13);

$expected =
		"SELECT [author].[id] AS [author_id], [author].[name] AS [author_name], [author].[web] AS [author_web], " .
		"[book].[id] AS [book_id], [book].[author_id] AS [book_author_id], [book].[reviewer_id] AS [book_reviewer_id], [book].[name] AS [book_name], " .
		"[book].[pubdate] AS [book_pubdate], [book].[description] AS [book_description], [book].[website] AS [book_website], [book].[available] AS [book_available] " .
		"FROM [author] " .
		"JOIN [book] ON [book].[author_id] = [author].[id] " .
		"WHERE [book_name] != 'The Pragmatic Programmer' AND LENGTH([book_name]) > 13";

Assert::equal($expected, $sql);

//////////

$sql = (string) $connection->select($queryHelper->formatSelect($bookReflection, $bookTable) + $queryHelper->formatSelect($authorReflection, $authorTable) + $queryHelper->formatSelect($bookReflection, 'book2'))
		->from($bookTable)
		->join($authorTable)->on('%n.%n = %n.%n', $bookTable, $bookReflection->getEntityProperty('author')->getColumn(), $authorTable, $authorReflection->getEntityProperty('id')->getColumn())
		->join('%n %n', $bookTable, 'book2')->on('%n.%n = %n.%n', 'book2', $bookReflection->getEntityProperty('author')->getColumn(), $authorTable, $authorReflection->getEntityProperty('id')->getColumn());

$expected =
		"SELECT [book].[id] AS [book_id], [book].[author_id] AS [book_author_id], [book].[reviewer_id] AS [book_reviewer_id], " .
		"[book].[name] AS [book_name], [book].[pubdate] AS [book_pubdate], [book].[description] AS [book_description], " .
		"[book].[website] AS [book_website], [book].[available] AS [book_available], [author].[id] AS [author_id], " .
		"[author].[name] AS [author_name], [author].[web] AS [author_web], [book2].[id] AS [book2_id], [book2].[author_id] AS [book2_author_id], " .
		"[book2].[reviewer_id] AS [book2_reviewer_id], [book2].[name] AS [book2_name], [book2].[pubdate] AS [book2_pubdate], " .
		"[book2].[description] AS [book2_description], [book2].[website] AS [book2_website], [book2].[available] AS [book2_available] " .
		"FROM [book] " .
		"JOIN [author] ON [book].[author_id] = [author].[id] " .
		"JOIN [book] [book2] ON [book2].[author_id] = [author].[id]";

Assert::equal($expected, $sql);

//////////

$sql = (string) $connection->select($queryHelper->formatSelect($authorReflection, $authorAlias, $authorPrefix) + $queryHelper->formatSelect($bookReflection, $bookAlias, $bookPrefix))
		->from([$authorTable => $authorAlias])
		->join('%n %n', $bookTable, $bookAlias)->on('%n.%n = %n.%n', $bookAlias, $bookReflection->getEntityProperty('author')->getColumn(), $authorAlias, $authorReflection->getEntityProperty('id')->getColumn())
		->where('%n != %s', $queryHelper->formatColumn($bookReflection->getEntityProperty('name'), $bookTable, $bookPrefix), 'The Pragmatic Programmer')
		->where('LENGTH(%n) > %i', $queryHelper->formatColumn($bookReflection->getEntityProperty('name'), $bookTable, $bookPrefix), 13);

$expected =
		"SELECT [spisovatel].[id] AS [s_id], [spisovatel].[name] AS [s_name], [spisovatel].[web] AS [s_web], " .
		"[knizka].[id] AS [b_id], [knizka].[author_id] AS [b_author_id], [knizka].[reviewer_id] AS [b_reviewer_id], [knizka].[name] AS [b_name], " .
		"[knizka].[pubdate] AS [b_pubdate], [knizka].[description] AS [b_description], [knizka].[website] AS [b_website], [knizka].[available] AS [b_available] " .
		"FROM [author] AS [spisovatel] " .
		"JOIN [book] [knizka] ON [knizka].[author_id] = [spisovatel].[id] " .
		"WHERE [b_name] != 'The Pragmatic Programmer' AND LENGTH([b_name]) > 13";

Assert::equal($expected, $sql);
