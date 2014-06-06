<?php

use LeanQuery\Hydrator;
use LeanQuery\HydratorMeta;
use LeanQuery\QueryHelper;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

$queryHelper = new QueryHelper($mapper);
$hydrator = new Hydrator($connection, $mapper);

$authorReflection = Author::getReflection($mapper);
$authorTable = 'author';
$authorPrefix = 'autor';

$bookReflection = Book::getReflection($mapper);
$bookTable = 'book';

$data = $connection->select($queryHelper->formatSelect(Author::getReflection($mapper), $authorTable, $authorPrefix) + $queryHelper->formatSelect(Book::getReflection($mapper), $bookTable))
		->from('%n AS %n', $authorTable, $authorTable)
		->join($bookTable)->on('%n.%n = %n.%n', $bookTable, $bookReflection->getEntityProperty('author')->getColumn(), $authorTable, $authorReflection->getEntityProperty('id')->getColumn())
		->where('%n != %s', $queryHelper->formatColumn($bookReflection->getEntityProperty('name'), $bookTable), 'The Pragmatic Programmer')
		->where('LENGTH(%n) > %i', $queryHelper->formatColumn($bookReflection->getEntityProperty('name'), $bookTable), 13)
		->fetchAll();

$hydratorMeta = new HydratorMeta;
$hydratorMeta->addTablePrefix('autor', 'author');
$hydratorMeta->addTablePrefix('book', 'book');

$hydratorMeta->addPrimaryKey('author', 'id');
$hydratorMeta->addPrimaryKey('book', 'id');

$hydratorMeta->addRelationship('book', 'book(book).author_id <=> autor(author).id');

$results = $hydrator->buildResultsGraph($data, $hydratorMeta, array('book'));

$authors = $results['autor'];
$output = '';

foreach ($authors as $author) {
	$author = $authors->getRow($author['id']);
	$output .= "$author->name\r\n";
	foreach ($author->referencing('book', 'author_id') as $book) {
		$output .= "\t$book->name\r\n";
		$authorAgain = $book->referenced('author', 'author_id');
		$output .= "\t\t$authorAgain->name\r\n";
		foreach ($authorAgain->referencing('book', 'author_id') as $bookAgain) {
			$output .= "\t\t\t$bookAgain->name\r\n";
		}
	}
}

Assert::count(1, $queries);

$expected =
		"SELECT [author].[id] AS [autor__id], [author].[name] AS [autor__name], [author].[web] AS [autor__web], " .
		"[book].[id] AS [book__id], [book].[author_id] AS [book__author_id], [book].[reviewer_id] AS [book__reviewer_id], [book].[name] AS [book__name], " .
		"[book].[pubdate] AS [book__pubdate], [book].[description] AS [book__description], [book].[website] AS [book__website], [book].[available] AS [book__available] " .
		"FROM [author] AS [author] " .
		"JOIN [book] ON [book].[author_id] = [author].[id] " .
		"WHERE [book__name] != 'The Pragmatic Programmer' AND LENGTH([book__name]) > 13";

Assert::equal($expected, $queries[0]);

$expected =
'Donald Knuth
	The Art of Computer Programming
		Donald Knuth
			The Art of Computer Programming
Martin Fowler
	Refactoring: Improving the Design of Existing Code
		Martin Fowler
			Refactoring: Improving the Design of Existing Code
Thomas H. Cormen
	Introduction to Algorithms
		Thomas H. Cormen
			Introduction to Algorithms
';

Assert::equal($expected, $output);
