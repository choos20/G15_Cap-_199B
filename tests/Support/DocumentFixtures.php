<?php

namespace Tests\Support;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use ZipArchive;

final class DocumentFixtures
{
    public static function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aZ1sAAAAASUVORK5CYII=');
    }

    public static function docx(): string
    {
        static $bytes;
        if ($bytes !== null) {
            return $bytes;
        }
        $path = tempnam(sys_get_temp_dir(), 'projectlink-test-');
        $image = $path.'.png';
        try {
            file_put_contents($image, self::png());
            $word = new PhpWord;
            $section = $word->addSection();
            $section->addHeader()->addText('ProjectLink preview test header');
            $section->addFooter()->addText('ProjectLink preview test footer');
            $section->addText('Shared document preview test', ['bold' => true, 'size' => 18]);
            $section->addText('The same original bytes must survive both upload workflows.');
            $table = $section->addTable(['borderSize' => 6]);
            $table->addRow();
            $table->addCell(3000)->addText('Activity');
            $table->addCell(3000)->addText('Budget');
            $table->addRow();
            $table->addCell(3000)->addText('Community project');
            $table->addCell(3000)->addText('1250');
            $section->addImage($image, ['width' => 36, 'height' => 36]);
            $section->addPageBreak();
            $section->addText('Second page of the proposal');
            $section->addText('Page breaks, tables, images, headers and footers are retained.');
            IOFactory::createWriter($word, 'Word2007')->save($path);

            return $bytes = file_get_contents($path);
        } finally {
            @unlink($path);
            @unlink($image);
        }
    }

    public static function xlsx(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'projectlink-xlsx-');
        try {
            $zip = new ZipArchive;
            $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Budget" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
            $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>Activity</t></is></c><c r="B1" t="inlineStr"><is><t>Budget</t></is></c></row><row r="2"><c r="A2" t="inlineStr"><is><t>Community project</t></is></c><c r="B2"><v>1250</v></c></row></sheetData></worksheet>');
            $zip->close();

            return file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    public static function pdf(): string
    {
        $stream = 'BT /F1 18 Tf 30 100 Td (ProjectLink PDF preview) Tj ET';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 400 200] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }
}
