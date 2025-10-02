<?php
// Asegúrate de que 'conn.php' establece la conexión PDO en la variable $conn
require_once 'conn.php';

$error_message = null; // Variable para manejar mensajes de error

if (isset($_POST['btn'])) {
    
    ob_start();

    try {
        $conn->beginTransaction();

        // 1. INSERCIÓN EN LA TABLA 'usuario'
        $sql_usuario = 'INSERT INTO usuario(nombre, documento, contacto) VALUES(?, ?, ?)';
        $insert_usuario = $conn->prepare($sql_usuario);
        
        $insert_usuario->execute([
            $_POST['nombre'],
            $_POST['documento'],
            $_POST['contacto']
        ]);
        
        $id_usuario = $conn->lastInsertId(); 
        if (!$id_usuario) {
             throw new Exception("Error al obtener ID de usuario. Revise AUTO_INCREMENT en 'idusuario'.");
        }


        // 2. INSERCIÓN EN LA TABLA 'vehiculo'
        $placa_upper = strtoupper(trim($_POST['placa'])); // Convertir placa a mayúsculas
        $sql_vehiculo = 'INSERT INTO vehiculo(tipo, placa, usuario_idusuario) VALUES(?, ?, ?)';
        $insert_vehiculo = $conn->prepare($sql_vehiculo);
        
        $insert_vehiculo->execute([
            $_POST['tipo'],
            $placa_upper, // Usar la placa en mayúsculas
            $id_usuario       
        ]);

        $id_vehiculo = $conn->lastInsertId(); 
        if (!$id_vehiculo) {
             throw new Exception("Error al obtener ID de vehículo. Revise AUTO_INCREMENT en 'idvehiculo'.");
        }


        // 3. INSERCIÓN EN LA TABLA 'registro'
        $hora_llegada = date('Y-m-d H:i:s'); 

        $sql_registro = 'INSERT INTO registro(hllegada, vehiculo_idvehiculo) VALUES(?, ?)';
        $insert_registro = $conn->prepare($sql_registro);

        $insert_registro->execute([
            $hora_llegada,
            $id_vehiculo 
        ]);

        $conn->commit();
        
        // Redirección al módulo de salida, llevando la placa
        header('Location: buscar_placa.php?placa=' . urlencode($placa_upper) . '&status=registered'); 
        exit();

    } catch (PDOException $e) {
        $conn->rollBack();
        // Detectar si el error es por duplicidad de placa
        if ($e->getCode() == '23000' && strpos($e->getMessage(), 'placa')) {
             $error_message = "Error de registro: La placa **" . htmlspecialchars($_POST['placa']) . "** ya está registrada. Si es una entrada nueva, verifique que la placa anterior haya marcado su salida.";
        } else {
             $error_message = "Error al guardar el registro: " . $e->getMessage();
        }
    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = "Error interno: " . $e->getMessage();
    }
}
?>


<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registro de Parqueadero</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

  <style>
    /* Estilos Personalizados */
    :root {
        --primary-color: #007bff;
        --secondary-color: #6c757d;
        --success-color: #28a745;
        --font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    body {
        font-family: var(--font-family);
        background-color: #f8f9fa; /* Fondo gris claro */
    }
    .card-custom {
        border-radius: 1.5rem; /* Bordes más redondeados */
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1); /* Sombra suave */
        border: none;
    }
    .form-label {
        font-weight: 600; /* Etiquetas en negrita */
        color: var(--secondary-color);
    }
    .btn-custom {
        background-color: var(--success-color); /* Botón de acción en verde */
        border: none;
        transition: background-color 0.3s;
    }
    .btn-custom:hover {
        background-color: #1e7e34;
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25);
    }
    .section-title {
        color: var(--primary-color);
        border-bottom: 2px solid #e9ecef;
        padding-bottom: 5px;
        margin-bottom: 20px;
    }
    </style>
</head>
<body class="bg-light">
  <div class="container mt-5 mb-5">
    <div class="card card-custom p-4 p-md-5">
      <h2 class="text-center mb-4 text-primary">Registro de Nueva Entrada</h2>

        <?php 
        // Mostrar mensaje de error si existe
        if (isset($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

            <form action="" method="POST"> 

                <h4 class="section-title">Datos del Propietario</h4>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="nombre" class="form-label">Nombre completo</label>
                <input type="text" class="form-control" id="nombre" name="nombre" required>
            </div>
            <div class="col-md-6 mb-3">
                <label for="documento" class="form-label">Documento de identidad</label>
                <input type="text" class="form-control" id="documento" name="documento" required>
            </div>
        </div>
        <div class="mb-4">
          <label for="contacto" class="form-label">Número de contacto</label>
          <input type="text" class="form-control" id="contacto" name="contacto">
        </div>

                <h4 class="section-title mt-4">Datos del Vehículo</h4>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="placa" class="form-label">Placa</label>
                <input type="text" class="form-control" id="placa" name="placa" required placeholder="Ej: ABC123">
            </div>
            <div class="col-md-6 mb-3">
                <label for="tipo" class="form-label">Tipo de vehículo</label>
                <select class="form-select" id="tipo" name="tipo" required>
                    <option value="" selected disabled>Seleccione una opción</option>
                    <option value="carro">Carro</option>
                    <option value="moto">Moto</option>
                    <option value="otro">Otro</option>
                </select>
            </div>
        </div>

                <div class="d-grid mt-4">
          <button type="submit" name="btn" class="btn btn-custom btn-lg rounded-pill">
                 Registrar Entrada al Parqueadero
            </button>
        </div>
      </form>
    </div>
  </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>