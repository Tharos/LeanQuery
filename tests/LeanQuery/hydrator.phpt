<?php

use LeanMapper\Connection;
use LeanQuery\Hydrator;
use LeanQuery\QueryHelper;
use Tester\Assert;
use Tester\Dumper;

require_once __DIR__ . '/../bootstrap.php';

$queryHelper = new QueryHelper($mapper);
$hydrator = new Hydrator($connection, $mapper);

$authorPrefix = 'autor';
$authorTable = 'author';
$bookPrefix = 'book';

$data = $connection->select($queryHelper->formatSelect(Author::class, $authorTable, $authorPrefix) + $queryHelper->formatSelect(Book::class, null, $bookPrefix))
		->from($queryHelper->formatFrom(Author::class))
		->join($queryHelper->formatJoin(Book::class))->on($queryHelper->formatOn(Book::class, 'author'))
		->where('%sql != %s', $queryHelper->formatColumn(Book::class, 'name', null, $bookPrefix), 'The Pragmatic Programmer')
		->where('LENGTH(%sql) > %i', $queryHelper->formatColumn(Book::class, 'name', null, $bookPrefix), 13)
		->fetchAll();

$tablesByPrefixes = array(
	'autor' => 'author',
	'book' => 'book',
);
$primaryKeysByTables = array(
	'author' => 'id',
	'book' => 'id',
);
$relationships = array(
	'book(book).author_id <=> autor(author).id',
);
$results = $hydrator->buildResultsGraph($data, $tablesByPrefixes, $primaryKeysByTables, $relationships);

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
		"SELECT [author].[id] AS [autor_id], [author].[name] AS [autor_name], [author].[web] AS [autor_web], " .
		"[book].[id] AS [book_id], [book].[author_id] AS [book_author_id], [book].[reviewer_id] AS [book_reviewer_id], [book].[name] AS [book_name], " .
		"[book].[pubdate] AS [book_pubdate], [book].[description] AS [book_description], [book].[website] AS [book_website], [book].[available] AS [book_available] " .
		"FROM [author] AS [author] " .
		"JOIN [book] ON [book].[author_id] = [author].[id] " .
		"WHERE [book_name] != 'The Pragmatic Programmer' AND LENGTH([book_name]) > 13";

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
