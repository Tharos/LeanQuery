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
		"SELECT [author].[id] AS [author__id], [author].[name] AS [author__name], [author].[web] AS [author__web], " .
		"[book].[id] AS [book__id], [book].[author_id] AS [book__author_id], [book].[reviewer_id] AS [book__reviewer_id], [book].[name] AS [book__name], " .
		"[book].[pubdate] AS [book__pubdate], [book].[description] AS [book__description], [book].[website] AS [book__website], [book].[available] AS [book__available] " .
		"FROM [author] " .
		"JOIN [book] ON [book].[author_id] = [author].[id] " .
		"WHERE [book__name] != 'The Pragmatic Programmer' AND LENGTH([book__name]) > 13";

Assert::equal($expected, $sql);

//////////

$sql = (string) $connection->select($queryHelper->formatSelect($bookReflection, $bookTable) + $queryHelper->formatSelect($authorReflection, $authorTable) + $queryHelper->formatSelect($bookReflection, 'book2'))
		->from($bookTable)
		->join($authorTable)->on('%n.%n = %n.%n', $bookTable, $bookReflection->getEntityProperty('author')->getColumn(), $authorTable, $authorReflection->getEntityProperty('id')->getColumn())
		->join('%n %n', $bookTable, 'book2')->on('%n.%n = %n.%n', 'book2', $bookReflection->getEntityProperty('author')->getColumn(), $authorTable, $authorReflection->getEntityProperty('id')->getColumn());

$expected =
		"SELECT [book].[id] AS [book__id], [book].[author_id] AS [book__author_id], [book].[reviewer_id] AS [book__reviewer_id], " .
		"[book].[name] AS [book__name], [book].[pubdate] AS [book__pubdate], [book].[description] AS [book__description], " .
		"[book].[website] AS [book__website], [book].[available] AS [book__available], [author].[id] AS [author__id], " .
		"[author].[name] AS [author__name], [author].[web] AS [author__web], [book2].[id] AS [book2__id], [book2].[author_id] AS [book2__author_id], " .
		"[book2].[reviewer_id] AS [book2__reviewer_id], [book2].[name] AS [book2__name], [book2].[pubdate] AS [book2__pubdate], " .
		"[book2].[description] AS [book2__description], [book2].[website] AS [book2__website], [book2].[available] AS [book2__available] " .
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
		"SELECT [spisovatel].[id] AS [s__id], [spisovatel].[name] AS [s__name], [spisovatel].[web] AS [s__web], " .
		"[knizka].[id] AS [b__id], [knizka].[author_id] AS [b__author_id], [knizka].[reviewer_id] AS [b__reviewer_id], [knizka].[name] AS [b__name], " .
		"[knizka].[pubdate] AS [b__pubdate], [knizka].[description] AS [b__description], [knizka].[website] AS [b__website], [knizka].[available] AS [b__available] " .
		"FROM [author] AS [spisovatel] " .
		"JOIN [book] [knizka] ON [knizka].[author_id] = [spisovatel].[id] " .
		"WHERE [b__name] != 'The Pragmatic Programmer' AND LENGTH([b__name]) > 13";

Assert::equal($expected, $sql);
