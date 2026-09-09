<?php

namespace App\Services;

use App\Models\AdminNewsDistributionItem;
use Illuminate\Support\Collection;
use RuntimeException;
use ZipArchive;

class AdminNewsDistributionXlsxExporter
{
    /** @param Collection<int, AdminNewsDistributionItem> $items */
    public function export(Collection $items): string
    {
        $path = tempnam(sys_get_temp_dir(), 'asya-yayin-raporu-');
        if ($path === false) {
            throw new RuntimeException('Excel raporu için geçici dosya oluşturulamadı.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Excel raporu oluşturulamadı.');
        }

        [$sheet, $relationships] = $this->worksheet($items);
        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRelationships());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships());
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        if ($relationships !== '') {
            $zip->addFromString('xl/worksheets/_rels/sheet1.xml.rels', $relationships);
        }
        $zip->close();

        return $path;
    }

    /** @param Collection<int, AdminNewsDistributionItem> $items
     * @return array{string, string}
     */
    private function worksheet(Collection $items): array
    {
        $headers = ['Tarih', 'Haber Başlığı', 'Ajans', 'İl', 'WordPress Hedefi', 'Site Adresi', 'Durum', 'Yayınlanan Haber Linki', 'Hata'];
        $rows = [$headers];
        $hyperlinks = [];

        foreach ($items as $item) {
            $publication = $item->publication;
            $rows[] = [
                ($publication?->published_at ?? $item->created_at)->format('d.m.Y H:i'),
                $item->distribution->title,
                $item->agency->name,
                $item->agency->province ?? '',
                $item->publishingTarget?->name ?? 'Hedef yok',
                $item->publishingTarget?->base_url ?? '',
                $publication?->status->label() ?? 'Gönderilemedi',
                $publication?->remote_url ?? '',
                $item->failure_message ?? $publication?->failure_message ?? '',
            ];

            if (filled($publication?->remote_url)) {
                $hyperlinks[count($rows)] = (string) $publication->remote_url;
            }
        }

        $rowXml = '';
        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;
            $cells = '';
            foreach ($row as $columnIndex => $value) {
                $reference = $this->columnName($columnIndex + 1).$excelRow;
                $style = $excelRow === 1 ? ' s="1"' : '';
                $cells .= '<c r="'.$reference.'" t="inlineStr"'.$style.'><is><t xml:space="preserve">'.$this->xml((string) $value).'</t></is></c>';
            }
            $rowXml .= '<row r="'.$excelRow.'">'.$cells.'</row>';
        }

        $hyperlinkXml = '';
        $relationshipXml = '';
        $relationshipIndex = 1;
        foreach ($hyperlinks as $row => $url) {
            $relationshipId = 'rId'.$relationshipIndex;
            $hyperlinkXml .= '<hyperlink ref="H'.$row.'" r:id="'.$relationshipId.'"/>';
            $relationshipXml .= '<Relationship Id="'.$relationshipId.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="'.$this->xml($url).'" TargetMode="External"/>';
            $relationshipIndex++;
        }

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols><col min="1" max="1" width="19" customWidth="1"/><col min="2" max="2" width="45" customWidth="1"/><col min="3" max="6" width="25" customWidth="1"/><col min="7" max="7" width="16" customWidth="1"/><col min="8" max="9" width="50" customWidth="1"/></cols>'
            .'<sheetData>'.$rowXml.'</sheetData><autoFilter ref="A1:I'.max(1, count($rows)).'"/>'
            .($hyperlinkXml !== '' ? '<hyperlinks>'.$hyperlinkXml.'</hyperlinks>' : '').'</worksheet>';

        $relationships = $relationshipXml === '' ? '' : '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relationshipXml.'</Relationships>';

        return [$sheet, $relationships];
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>';
    }

    private function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Yayın Raporu" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0F766E"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs></styleSheet>';
    }
}
