<?php

declare(strict_types=1);

final class PdfReportWriter
{
    public function write(string $path,array $report): void
    {
        $lines=[(string)$report['title'],'Generado por GymTrack · '.date('d/m/Y H:i'),''];$lines[]=implode(' | ',$report['headers']);foreach(array_slice($report['rows'],0,45) as $row)$lines[]=implode(' | ',array_map(static fn($v):string=>(string)$v,$row));if(count($report['rows'])>45)$lines[]='… El reporte contiene más filas; use Excel para el detalle completo.';
        $content="BT\n/F1 9 Tf\n40 800 Td\n12 TL\n";foreach($lines as $line){$encoded=iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$line)?:$line;$encoded=str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$encoded);$content.='('.substr($encoded,0,110).") Tj\nT*\n";}$content.="ET";
        $objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',2=>'<< /Type /Pages /Kids [3 0 R] /Count 1 >>',3=>'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',4=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',5=>'<< /Length '.strlen($content).' >>' . "\nstream\n".$content."\nendstream"];
        $pdf="%PDF-1.4\n";$offsets=[0];foreach($objects as $id=>$object){$offsets[$id]=strlen($pdf);$pdf.=$id." 0 obj\n".$object."\nendobj\n";}$xref=strlen($pdf);$pdf.="xref\n0 6\n0000000000 65535 f \n";for($i=1;$i<=5;$i++)$pdf.=sprintf('%010d 00000 n ',$offsets[$i])."\n";$pdf.="trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
        if(file_put_contents($path,$pdf)===false)throw new RuntimeException('No se pudo escribir el archivo PDF.');
    }
}
