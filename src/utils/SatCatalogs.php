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

    /**
     * Validar RFC contra formato SAT
     */
    public static function validateRfc(string $rfc): array {
        // Convertir a mayúsculas y eliminar espacios
        $rfc = strtoupper(trim($rfc));
        
        // Validar longitud
        if (strlen($rfc) !== 12 && strlen($rfc) !== 13) {
            return [
                'valid' => false,
                'message' => 'El RFC debe tener 12 caracteres (personas morales) o 13 caracteres (personas físicas)'
            ];
        }
        
        // Validar formato con regex
        // Personas morales: 3 letras + 6 dígitos + 3 caracteres alfanuméricos
        // Personas físicas: 4 letras + 6 dígitos + 3 caracteres alfanuméricos
        $pattern = strlen($rfc) === 12 
            ? '/^[A-Z&Ñ]{3}[0-9]{6}[A-Z0-9]{3}$/'
            : '/^[A-Z&Ñ]{4}[0-9]{6}[A-Z0-9]{3}$/';
        
        if (!preg_match($pattern, $rfc)) {
            return [
                'valid' => false,
                'message' => 'Formato de RFC inválido'
            ];
        }
        
        // Validar contra RFCs genéricos
        $genericRfcs = ['XAXX010101000', 'XEXX010101000'];
        if (in_array($rfc, $genericRfcs)) {
            return [
                'valid' => true,
                'is_generic' => true,
                'message' => 'RFC genérico (Público en General)'
            ];
        }
        
        return [
            'valid' => true,
            'is_generic' => false,
            'message' => 'RFC válido'
        ];
    }

    /**
     * Validar RFC con cálculo de dígito verificador (más estricto)
     */
    public static function validateRfcWithChecksum(string $rfc): array {
        $rfc = strtoupper(trim($rfc));
        $basicValidation = self::validateRfc($rfc);
        
        if (!$basicValidation['valid']) {
            return $basicValidation;
        }
        
        // Extraer dígitos numéricos (sin la letra/dígito verificador final)
        $numericPart = substr($rfc, 3, 6);
        
        if (!ctype_digit($numericPart)) {
            return [
                'valid' => false,
                'message' => 'La parte numérica del RFC debe contener solo dígitos'
            ];
        }
        
        // Validar que la fecha sea razonable (no futura, no muy antigua)
        $year = (int)substr($numericPart, 0, 2);
        $month = (int)substr($numericPart, 2, 2);
        $day = (int)substr($numericPart, 4, 2);
        
        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return [
                'valid' => false,
                'message' => 'La fecha en el RFC es inválida'
            ];
        }
        
        return $basicValidation;
    }
}
?>
