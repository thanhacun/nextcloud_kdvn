<?php

declare(strict_types=1);

namespace OCA\KdvnRooms\Service;

/**
 * Reads the first sheet of an .xlsx file (or a .csv file) into rows of text. Only what a room list needs:
 * no formulas, dates or styles. xlsx is a zip of XML files, so PHP's ZipArchive and SimpleXML are enough.
 */
class SheetReader {
	public const MAX_BYTES = 2 * 1024 * 1024;
	public const MAX_ROWS = 500;
	private const MAX_XML_BYTES = 20 * 1024 * 1024;

	/**
	 * @return string[][] rows of cells, trailing empty cells removed, completely empty rows dropped
	 * @throws UserError when the file is not a usable .xlsx or .csv
	 */
	public function read(string $path, string $originalName): array {
		if (filesize($path) > self::MAX_BYTES) {
			throw new UserError('The file is too big (max 2 MB).');
		}
		$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
		$rows = match ($extension) {
			'xlsx' => $this->readXlsx($path),
			'csv', 'txt' => $this->readCsv($path),
			default => throw new UserError('Use an Excel file (.xlsx) or a .csv file. Old .xls files: open them in Excel and save as .xlsx.'),
		};
		$rows = array_values(array_filter($rows, fn (array $row) => array_filter($row, fn ($c) => $c !== '') !== []));
		if (count($rows) > self::MAX_ROWS + 1) {
			throw new UserError('Too many rows (max ' . self::MAX_ROWS . ' rooms per file). Split the file.');
		}
		return $rows;
	}

	/** @return string[][] */
	private function readCsv(string $path): array {
		$text = (string)file_get_contents($path);
		$text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
		if (!mb_check_encoding($text, 'UTF-8')) {
			throw new UserError('Save the .csv as "CSV UTF-8" in Excel, or use .xlsx.');
		}
		// Excel with a European/Vietnamese region saves with ";" instead of ",".
		$firstLine = strtok($text, "\n") ?: '';
		$delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
		$handle = fopen('php://temp', 'r+');
		fwrite($handle, $text);
		rewind($handle);
		$rows = [];
		while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
			$rows[] = $this->trimRow(array_map(fn ($c) => trim((string)$c), $row));
		}
		fclose($handle);
		return $rows;
	}

	/** @return string[][] */
	private function readXlsx(string $path): array {
		if (!class_exists(\ZipArchive::class)) {
			throw new UserError('This server cannot read .xlsx files (PHP zip is missing). Use a .csv file.');
		}
		$zip = new \ZipArchive();
		if ($zip->open($path) !== true) {
			throw new UserError('This is not a valid .xlsx file.');
		}
		try {
			$sheetPath = $this->firstSheetPath($zip);
			$strings = $this->sharedStrings($zip);
			$sheet = $this->xml($zip, $sheetPath);
		} finally {
			$zip->close();
		}
		$rows = [];
		foreach ($sheet->sheetData->row ?? [] as $rowNode) {
			$row = [];
			$next = 0;
			foreach ($rowNode->c as $cell) {
				// Some writers leave out the cell reference ("C7"): then the cells are simply in order.
				$ref = (string)$cell['r'];
				$column = $ref === '' ? $next : $this->columnIndex($ref);
				$next = $column + 1;
				$type = (string)$cell['t'];
				$value = match ($type) {
					's' => $strings[(int)$cell->v] ?? '',
					'inlineStr' => $this->textOf($cell->is),
					'b' => ((string)$cell->v) === '1' ? 'yes' : 'no',
					default => (string)$cell->v, // numbers and formula results
				};
				if (is_numeric($value) && str_contains($value, '.') && (float)$value == floor((float)$value)) {
					$value = (string)(int)(float)$value; // 30.0 -> 30
				}
				$row[$column] = trim($value);
			}
			$line = [];
			if ($row !== []) {
				for ($i = 0; $i <= max(array_keys($row)); $i++) {
					$line[] = $row[$i] ?? '';
				}
			}
			$rows[] = $this->trimRow($line);
		}
		return $rows;
	}

	private function firstSheetPath(\ZipArchive $zip): string {
		// Follow workbook.xml -> its relationships, so a workbook whose first sheet is not "sheet1.xml" still works.
		try {
			$workbook = $this->xml($zip, 'xl/workbook.xml');
			$rels = $this->xml($zip, 'xl/_rels/workbook.xml.rels');
			$rid = (string)($workbook->sheets->sheet[0]->attributes('r', true)['id'] ?? '');
			foreach ($rels->Relationship as $rel) {
				if ((string)$rel['Id'] === $rid) {
					$target = ltrim((string)$rel['Target'], '/');
					return str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
				}
			}
		} catch (UserError) {
			// fall through to the default name
		}
		return 'xl/worksheets/sheet1.xml';
	}

	/** @return string[] */
	private function sharedStrings(\ZipArchive $zip): array {
		if ($zip->locateName('xl/sharedStrings.xml') === false) {
			return [];
		}
		$out = [];
		foreach ($this->xml($zip, 'xl/sharedStrings.xml')->si ?? [] as $si) {
			$out[] = $this->textOf($si);
		}
		return $out;
	}

	/** Text of a shared/inline string, joining rich-text runs (<r><t>..</t></r>). */
	private function textOf(?\SimpleXMLElement $node): string {
		if ($node === null) {
			return '';
		}
		$text = '';
		foreach ($node->xpath('.//*[local-name()="t"][not(ancestor::*[local-name()="rPh"])]') ?: [] as $t) {
			$text .= (string)$t;
		}
		return $text;
	}

	private function xml(\ZipArchive $zip, string $name): \SimpleXMLElement {
		$stat = $zip->statName($name);
		if ($stat === false) {
			throw new UserError('This .xlsx file has no worksheet. Is it an Excel file?');
		}
		if ($stat['size'] > self::MAX_XML_BYTES) {
			throw new UserError('This worksheet is too big to read.');
		}
		$xml = simplexml_load_string((string)$zip->getFromName($name), \SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOCDATA);
		if ($xml === false) {
			throw new UserError('This .xlsx file could not be read.');
		}
		return $xml;
	}

	/** "C7" -> 2, "AA1" -> 26 */
	private function columnIndex(string $ref): int {
		preg_match('/^[A-Z]+/i', $ref, $m);
		$n = 0;
		foreach (str_split(strtoupper($m[0] ?? 'A')) as $ch) {
			$n = $n * 26 + (ord($ch) - 64);
		}
		return max(0, $n - 1);
	}

	/** @param string[] $row */
	private function trimRow(array $row): array {
		while ($row !== [] && end($row) === '') {
			array_pop($row);
		}
		return array_values($row);
	}
}
