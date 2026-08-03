<?php
/**
 * Catálogos Oficiales del SAT para CFDI 4.0
 * Truper Platform
 */

class SatCatalogs {
    public static function getTaxRegimes(): array {
        return [
            '601' => '601 - General de Ley Personas Morales',
            '603' => '603 - Personas Morales con Fines no Lucrativos',
            '605' => '605 - Sueldos y Salarios e Ingresos Similares a Salarios',
            '606' => '606 - Arrendamiento',
            '607' => '607 - Régimen de Enajenación o Adquisición de Bienes',
            '608' => '608 - Demás ingresos',
            '610' => '610 - Residentes en el Extranjero sin Establecimiento Permanente en México',
            '612' => '612 - Personas Físicas con Actividades Empresariales y Profesionales',
            '614' => '614 - Ingresos por Intereses',
            '615' => '615 - Régimen de los ingresos por obtención de premios',
            '616' => '616 - Sin obligaciones fiscales',
            '620' => '620 - Sociedades Cooperativas de Producción que optan por diferir sus ingresos',
            '621' => '621 - Incorporación Fiscal',
            '622' => '622 - Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras',
            '623' => '623 - Opcional para Grupos de Sociedades',
            '624' => '624 - Coordinados',
            '625' => '625 - Régimen de las Actividades Empresariales con ingresos a través de Plataformas Tecnológicas',
            '626' => '626 - Régimen Simplificado de Confianza (RESICO)'
        ];
    }

    public static function getCfdiUses(): array {
        return [
            'G01' => 'G01 - Adquisición de mercancías',
            'G02' => 'G02 - Devoluciones, descuentos o bonificaciones',
            'G03' => 'G03 - Gastos en general',
            'I01' => 'I01 - Construcciones',
            'I02' => 'I02 - Mobiliario y equipo de oficina por inversiones',
            'I03' => 'I03 - Equipo de transporte',
            'I04' => 'I04 - Equipo de cómputo y accesorios',
            'I08' => 'I08 - Otra maquinaria y equipo',
            'D01' => 'D01 - Honorarios médicos, dentales y gastos hospitalarios',
            'S01' => 'S01 - Sin efectos fiscales',
            'CP01' => 'CP01 - Pagos'
        ];
    }

    public static function getTaxRegimeLabel(string $code): string {
        $regimes = self::getTaxRegimes();
        return $regimes[$code] ?? "Regimen {$code}";
    }

    public static function getCfdiUseLabel(string $code): string {
        $uses = self::getCfdiUses();
        return $uses[$code] ?? "Uso {$code}";
    }
}
?>
