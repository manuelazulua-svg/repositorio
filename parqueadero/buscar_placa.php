<?php 
// Asegúrate de que 'conn.php' establece la conexión PDO en la variable $conn
require_once 'conn.php';

// Variables iniciales
$placa_a_buscar = null;
$mensaje = null;
$registro_id = null;
$datos_estadia = null;

// Determinar la fuente de la placa: ¿POST (búsqueda manual) o GET (redirección automática)?
if (isset($_POST['buscar_btn'])) {
    // Si viene de la búsqueda manual, limpiar y convertir a mayúsculas
    $placa_a_buscar = strtoupper(trim($_POST['placa']));
} elseif (isset($_GET['placa'])) {
    // Si viene de la redirección, limpiar y convertir a mayúsculas
    $placa_a_buscar = strtoupper(trim($_GET['placa']));
    if (isset($_GET['status']) && $_GET['status'] == 'registered') {
        // Mensaje de éxito después de la redirección automática
        $mensaje = "✅ ¡Entrada Registrada! Propietario y vehículo guardados. Presione 'Registrar Salida' cuando sea necesario.";
    }
}

// Inicia la lógica de búsqueda solo si se tiene una placa
if ($placa_a_buscar) {
    
    // 1. Buscar el vehículo por placa
    // La placa se busca usando la variable ya convertida a mayúsculas ($placa_a_buscar)
    $sql_vehiculo = "SELECT idvehiculo FROM vehiculo WHERE placa = ?";
    $stmt_vehiculo = $conn->prepare($sql_vehiculo);
    $stmt_vehiculo->execute([$placa_a_buscar]);
    $vehiculo = $stmt_vehiculo->fetch(PDO::FETCH_ASSOC);

    if (!$vehiculo) {
        // SCENARIO 1: Placa no encontrada en la tabla 'vehiculo'
        $mensaje = "❌ La placa **{$placa_a_buscar}** no está registrada en nuestra base de datos. Verifique si la registró.";
    } else {
        $id_vehiculo = $vehiculo['idvehiculo'];
        
        // 2. Buscar el registro de entrada ACTIVO (hsalida es NULL)
        $sql_registro = "
            SELECT r.idregistro, r.hllegada, v.placa, u.nombre
            FROM registro r
            JOIN vehiculo v ON r.vehiculo_idvehiculo = v.idvehiculo
            JOIN usuario u ON v.usuario_idusuario = u.idusuario
            WHERE r.vehiculo_idvehiculo = ? AND r.hsalida IS NULL
            ORDER BY r.idregistro DESC 
            LIMIT 1";

        $stmt_registro = $conn->prepare($sql_registro);
        $stmt_registro->execute([$id_vehiculo]);
        $datos_estadia = $stmt_registro->fetch(PDO::FETCH_ASSOC);

        if ($datos_estadia) {
            $registro_id = $datos_estadia['idregistro'];
            
            // Si hay un registro activo, establece un mensaje de éxito si no hay uno de registro
            if (!isset($_GET['status']) || $_GET['status'] != 'registered') {
                 $mensaje = "✅ ¡Estadía Activa! Lista para registrar la salida.";
            }

        } else {
            // SCENARIO 2: Placa existe, pero no tiene entrada activa (ya marcó su salida)
            $mensaje = "⚠️ La placa **{$placa_a_buscar}** fue encontrada, pero no tiene una entrada activa registrada (quizás ya marcó su salida).";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Buscar y Registrar Salida</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <style>
    /* Variables y Estilos Personalizados */
    :root {
        --primary-color: #007bff; /* Azul */
        --success-color: #28a745; /* Verde */
        --danger-color: #dc3545; /* Rojo */
        --warning-color: #ffc107; /* Amarillo */
        --font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    body {
        font-family: var(--font-family);
        background-color: #f8f9fa; /* Color de fondo claro */
    }
    .card-custom {
        border-radius: 1rem;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }
    .result-info {
        background-color: #e9ecef;
        padding: 20px;
        border-radius: 0.5rem;
    }
    .output-btn {
        background-color: var(--success-color); /* Botón de salida en verde */
        border: none;
        transition: background-color 0.3s;
    }
    .output-btn:hover {
        background-color: #1e7e34;
    }
    /* Estilos para los mensajes de estado personalizados */
    .alert-success-custom {
        background-color: #d4edda;
        color: var(--success-color);
        border-color: #c3e6cb;
    }
    .alert-danger-custom {
        background-color: #f8d7da;
        color: var(--danger-color);
        border-color: #f5c6cb;
    }
    .alert-warning-custom {
        background-color: #fff3cd;
        color: #856404; /* Color oscuro para contraste en warning */
        border-color: #ffeeba;
    }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="card card-custom p-4">
            <h2 class="text-center mb-4 text-primary">Módulo de Búsqueda y Salida </h2>

            <form method="POST" action="buscar_placa.php" class="mb-4">
                <div class="input-group input-group-lg">
                    <input type="text" class="form-control" 
                           placeholder="Ingrese la Placa del Vehículo (Ej: ABC123)" 
                           name="placa" 
                           value="<?php echo htmlspecialchars($placa_a_buscar ?? ''); ?>"
                           required>
                    <button class="btn btn-primary" type="submit" name="buscar_btn">
                        <i class="bi bi-search"></i> Buscar
                    </button>
                </div>
            </form>

            <hr>

            <?php if (isset($mensaje)): ?>
                <?php 
                    // Determina la clase del mensaje basada en el contenido del icono
                    $alert_class = 'alert-info';
                    if (strpos($mensaje, '✅') !== false) {
                        $alert_class = 'alert-success-custom';
                    } elseif (strpos($mensaje, '❌') !== false) {
                        $alert_class = 'alert-danger-custom';
                    } elseif (strpos($mensaje, '⚠️') !== false) {
                        $alert_class = 'alert-warning-custom';
                    }
                ?>
                <div class="alert <?php echo $alert_class; ?> p-3" role="alert">
                    <?php echo $mensaje; ?>
                </div>
            <?php endif; ?>

            <?php if ($registro_id): ?>
                <div class="result-info">
                    <h3 class="text-success mb-3">Vehículo Estacionado (Registro Activo)</h3>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Propietario:</strong> <?php echo htmlspecialchars($datos_estadia['nombre'] ?? 'N/A'); ?></p>
                            <p class="mb-1"><strong>Placa:</strong> <span class="badge bg-primary fs-6"><?php echo htmlspecialchars($datos_estadia['placa'] ?? 'N/A'); ?></span></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>ID de Registro:</strong> <?php echo htmlspecialchars($registro_id); ?></p>
                            <p class="mb-1"><strong>Hora de Entrada:</strong> <?php echo htmlspecialchars($datos_estadia['hllegada'] ?? 'N/A'); ?></p>
                        </div>
                    </div>

                    <hr>
                    
                    <form method="POST" action="procesar_salida.php">
                        <input type="hidden" name="registro_id" value="<?php echo $registro_id; ?>">
                        <div class="d-grid mt-3">
                            <button type="submit" name="salir_btn" class="btn btn-lg output-btn">
                                Registrar Salida y Generar Factura
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

        </div>
    </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>