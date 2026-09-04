<?php
/**
 * Servicio SpreadsheetReportWriter. Encapsula una responsabilidad transversal para que controladores y modelos no dupliquen reglas.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

final class SpreadsheetReportWriter
{
    public function write(string $path,array $report): void
    {
        $rows=array_merge([$report['headers']],$report['rows']);$sheet='';
        foreach($rows as $rowIndex=>$row){$cells='';foreach(array_values($row) as $columnIndex=>$value){$ref=$this->column($columnIndex+1).($rowIndex+1);$cells.='<c r="'.$ref.'" t="inlineStr"><is><t xml:space="preserve">'.$this->xml((string)$value).'</t></is></c>';}$sheet.='<row r="'.($rowIndex+1).'">'.$cells.'</row>';}
        $files=[
            '[Content_Types].xml'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>',
            '_rels/.rels'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="GymTrack" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>',
            'xl/worksheets/sheet1.xml'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheet.'</sheetData></worksheet>',
        ];
        $zipPath=$path.'.zip';if(is_file($zipPath))unlink($zipPath);$archive=new PharData($zipPath,0,null,Phar::ZIP);foreach($files as $name=>$contents)$archive->addFromString($name,$contents);unset($archive);if(!rename($zipPath,$path))throw new RuntimeException('No se pudo finalizar el archivo Excel.');
    }
    private function xml(string $value): string{return htmlspecialchars($value,ENT_XML1|ENT_QUOTES,'UTF-8');}
    private function column(int $number): string{$name='';while($number>0){$number--;$name=chr(65+($number%26)).$name;$number=intdiv($number,26);}return $name;}
}
