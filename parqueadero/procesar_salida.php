<?php
// Incluye la conexión a la base de datos
require_once 'conn.php';

// --- VERIFICACIÓN DE LIBRERÍA FPDF ---
// Aseguramos que la librería FPDF exista antes de intentar usarla.
$fpdf_path = 'fpdf.php'; 
if (!file_exists($fpdf_path)) {
    die("
        <h1>❌ Error Fatal: Librería FPDF No Encontrada</h1>
        <p>No se puede generar el PDF porque el archivo <b>'fpdf.php'</b> no está en la ubicación esperada.</p>
        <p>Asegúrese de que el archivo esté en la misma carpeta que 'procesar_salida.php'.</p>
    ");
}

// Incluye la librería FPDF
require_once $fpdf_path;

if (isset($_POST['salir_btn']) && isset($_POST['registro_id'])) {
    
    $registro_id = $_POST['registro_id'];
    $hora_salida = date('Y-m-d H:i:s');
    $valor_hora = 3000; // El valor de la hora es $3000

    try {
        // Inicia la transacción para garantizar que la salida y la factura se guarden juntas
        $conn->beginTransaction();

        // 1. CONSULTAR la hora de llegada antes de actualizar
        $sql_select = "SELECT hllegada, vehiculo_idvehiculo FROM registro WHERE idregistro = ?";
        $stmt_select = $conn->prepare($sql_select);
        $stmt_select->execute([$registro_id]);
        $registro = $stmt_select->fetch(PDO::FETCH_ASSOC);

        if (!$registro) {
            throw new Exception("Registro de entrada no encontrado. ID: " . $registro_id);
        }
        
        // CÁLCULO DE TIEMPO Y COSTO
        $hora_llegada = new DateTime($registro['hllegada']);
        $hora_salida_dt = new DateTime($hora_salida);
        
        $intervalo = $hora_llegada->diff($hora_salida_dt);
        
        // Convertir el intervalo a minutos totales
        $minutos_totales = $intervalo->days * 24 * 60;
        $minutos_totales += $intervalo->h * 60;
        $minutos_totales += $intervalo->i;
        
        // Redondeo: Si hay segundos, sumamos un minuto (casi nunca pasa, pero es seguro)
        if ($intervalo->s > 0) {
            $minutos_totales += 1;
        }

        // Calcular el tiempo en horas decimales (para guardar en la BD)
        $tiempo_decimal = $minutos_totales / 60; 
        
        // Regla de Cobro: Se cobra la hora completa si se pasa del minuto 0.
        // ceil() redondea al entero superior.
        $horas_cobrables = ceil($tiempo_decimal); 
        
        $valor_total = $horas_cobrables * $valor_hora;
        
        // 2. ACTUALIZAR la tabla 'registro' con la hora de salida
        $sql_update_registro = "UPDATE registro SET hsalida = ? WHERE idregistro = ?";
        $stmt_update = $conn->prepare($sql_update_registro);
        $stmt_update->execute([$hora_salida, $registro_id]);

        // 3. INSERCIÓN EN LA TABLA 'factura'
        $sql_factura = "INSERT INTO factura(tiempo, valor, fecha, registro_idregistro) VALUES(?, ?, ?, ?)";
        $stmt_factura = $conn->prepare($sql_factura);
        
        $stmt_factura->execute([
            round($tiempo_decimal, 2), // Guardamos las horas decimales
            $valor_total,
            $hora_salida,
            $registro_id
        ]);

        // Confirma la transacción
        $conn->commit();
        
        // ---------------------------------------------------------------------
        // PASO 4: GENERAR PDF (usando la información calculada)
        // ---------------------------------------------------------------------

        // Consultar datos adicionales (placa y nombre del propietario) para el PDF
        $sql_datos = "
            SELECT v.placa, u.nombre
            FROM vehiculo v
            JOIN usuario u ON v.usuario_idusuario = u.idusuario
            WHERE v.idvehiculo = ?";
        $stmt_datos = $conn->prepare($sql_datos);
        $stmt_datos->execute([$registro['vehiculo_idvehiculo']]);
        $datos_pdf = $stmt_datos->fetch(PDO::FETCH_ASSOC);
        
        // Crear el objeto PDF (FPDF)
        $pdf = new FPDF(); // Esto fallaba porque 'fpdf.php' no se encontraba antes.
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        
        // --- Contenido del PDF ---
        
        // Título
        $pdf->Cell(0, 10, 'FACTURA DE PARQUEADERO', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 5, 'Fecha de Emision: ' . $hora_salida, 0, 1, 'C');
        $pdf->Ln(10);
        
        // Detalles del Vehículo/Cliente
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(40, 7, 'Cliente:', 0, 0);
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 7, $datos_pdf['nombre'], 0, 1);
        
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(40, 7, 'Placa:', 0, 0);
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 7, $datos_pdf['placa'], 0, 1);
        $pdf->Ln(5);
        
        // Detalles de la Estadia (Tabla)
        $pdf->SetFillColor(200, 220, 255);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(70, 7, 'CONCEPTO', 1, 0, 'C', true);
        $pdf->Cell(40, 7, 'DATO', 1, 0, 'C', true);
        $pdf->Cell(40, 7, 'VALOR', 1, 1, 'C', true);
        
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(70, 7, 'Hora de Entrada', 1, 0);
        $pdf->Cell(40, 7, $registro['hllegada'], 1, 0);
        $pdf->Cell(40, 7, '', 1, 1);
        
        $pdf->Cell(70, 7, 'Hora de Salida', 1, 0);
        $pdf->Cell(40, 7, $hora_salida, 1, 0);
        $pdf->Cell(40, 7, '', 1, 1);
        
        $pdf->Cell(70, 7, 'Tiempo Cobrado', 1, 0);
        $pdf->Cell(40, 7, $horas_cobrables . ' hora(s)', 1, 0);
        $pdf->Cell(40, 7, '', 1, 1);
        
        $pdf->Cell(70, 7, 'Tarifa por Hora', 1, 0);
        $pdf->Cell(40, 7, '', 1, 0);
        $pdf->Cell(40, 7, '$' . number_format($valor_hora, 0, ',', '.'), 1, 1, 'R');

        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(70, 10, 'VALOR TOTAL A PAGAR', 1, 0, 'R', true);
        $pdf->Cell(80, 10, '$' . number_format($valor_total, 0, ',', '.'), 1, 1, 'R', true);

        // Salida del PDF: El navegador forzará la descarga
        $pdf->Output('D', 'Factura_' . $datos_pdf['placa'] . '_' . date('Ymd_His') . '.pdf');

    } catch (PDOException $e) {
        $conn->rollBack();
        echo "<h1>🚨 Error de Base de Datos</h1><p>Algo falló durante la actualización o inserción de la factura:</p><pre>" . $e->getMessage() . "</pre>";
    } catch (Exception $e) {
        echo "<h1>🚨 Error General</h1><p>" . $e->getMessage() . "</p>";
    }
} else {
    // Redirección si se accede directamente
    header('Location: buscar_placa.php');
    exit();
}
?>