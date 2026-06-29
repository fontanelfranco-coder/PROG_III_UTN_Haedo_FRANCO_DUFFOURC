using System;
using MySql.Data.MySqlClient; 

namespace Progra3Card.Administrativo
{
    class Program
    {
        private static string connectionString = "Server=localhost;Database=mi_banco_db;Uid=root;Pwd=Totticapo18@;";
        static void Main(string[] args)
        {
            bool salir = false;
            while (!salir)
            {
                Console.Clear();
                Console.WriteLine("========================================");
                Console.WriteLine("    SISTEMA ADMINISTRATIVO PROGRA3CARD   ");
                Console.WriteLine("========================================");
                Console.WriteLine("1. Emitir Nueva Tarjeta (Alta de Cliente)");
                Console.WriteLine("2. Listar Tarjetas");
                Console.WriteLine("3. Ver Detalle de una Tarjeta / Cliente");
                Console.WriteLine("4. Eliminar Tarjeta (Baja de Sistema)");
                Console.WriteLine("5. Emitir Nueva Liquidación Mensual");
                Console.WriteLine("6. Salir");
                Console.WriteLine("========================================");
                Console.Write("Seleccione una opción: ");

                switch (Console.ReadLine())
                {
                    case "1": MenuEmitirTarjeta(); break; //permite dar de alta un cliente y emitirle una tarjeta
                    case "2": MenuListarTarjetas(); break;
                    case "3": MenuVerDetalleTarjeta(); break;
                    case "4": MenuEliminarTarjeta(); break;
                    case "5": MenuEmitirLiquidacion(); break; //Solicitar el número de cuenta, período, vencimiento y montos para insertar el nuevo resumen
                    case "6": salir = true; break;
                    default:
                        Console.WriteLine("Opción no válida. Presione una tecla para continuar...");
                        Console.ReadKey();
                        break;
                }
            }
        }

        static void MenuEmitirTarjeta()
        {
            Console.Clear();
            Console.WriteLine("--- EMITIR NUEVA TARJETA (ALTA DE CLIENTE) ---");

        try
        {
            // 1. Recolección de datos del Usuario
            Console.Write("Ingrese Documento del Cliente: ");
            string documento = Console.ReadLine();

            Console.Write("Tipo de Documento (DNI o PASAPORTE): ");
            string tipoDoc = Console.ReadLine().ToUpper();

            Console.Write("Nombre: ");
            string nombre = Console.ReadLine();

            Console.Write("Apellido: ");
            string apellido = Console.ReadLine();

            Console.Write("Fecha de Nacimiento (YYYY-MM-DD): ");
            DateTime fechaNacimiento = Convert.ToDateTime(Console.ReadLine());

            Console.Write("Email: ");
            string email = Console.ReadLine();

            // 2. Recolección de datos de la Tarjeta
            Console.Write("\nIngrese Número de Tarjeta (16 dígitos): ");
            string numeroTarjeta = Console.ReadLine();

            Console.WriteLine("\nBancos disponibles: Banco Nación, Banco Provincia, Banco Galicia, Banco Santander, Banco BBVA, Banco Macro");
            Console.Write("Ingrese el Banco Emisor (escriba tal cual las opciones): ");
            string bancoEmisor = Console.ReadLine();

            // 3. Llamado a la función que hace la magia en la base de datos
            bool exito = RegistrarClienteYTarjeta(documento, tipoDoc, nombre, apellido, fechaNacimiento, email, numeroTarjeta, bancoEmisor);

            if (exito)
                Console.WriteLine("\n✅ ¡Cliente y Tarjeta registrados con éxito!");
            else
                Console.WriteLine("\n❌ Hubo un problema al registrar la tarjeta.");
        }
        catch (FormatException)
        {
            Console.WriteLine("\n❌ Error: Formato de fecha incorrecto. Asegúrese de usar YYYY-MM-DD.");
        }

        Console.WriteLine("\nPresione una tecla para volver al menú...");
        Console.ReadKey(); 
        }

        static void MenuListarTarjetas()
        {
            Console.Clear();
            Console.WriteLine("--- LISTADO DE TARJETAS ---");
            Console.WriteLine("{0,-12} {1,-18} {2,-20} {3,-15}", "N° Cuenta", "N° Tarjeta", "Banco Emisor", "DNI Titular");
            Console.WriteLine(new string('-', 70));

            ObtenerYMostrarTarjetas();

            Console.WriteLine("\nPresione una tecla para volver al menú...");
            Console.ReadKey();
        }

        static void MenuEmitirLiquidacion()
        {
            Console.Clear();
            Console.WriteLine("--- EMITIR NUEVA LIQUIDACIÓN MENSUAL ---");
            
            try
            {
                Console.Write("Ingrese el número de cuenta de la tarjeta: ");
                int cuenta = Convert.ToInt32(Console.ReadLine());

                Console.Write("Ingrese el período de la liquidación (YYYY-MM): ");
                string periodo = Console.ReadLine();

                Console.Write("Ingrese la fecha de vencimiento (YYYY-MM-DD): ");
                DateTime fechaVencimiento = Convert.ToDateTime(Console.ReadLine());

                Console.Write("Ingrese el monto total a pagar: ");
                decimal montoTotal = Convert.ToDecimal(Console.ReadLine());

                Console.Write("Ingrese el monto mínimo a pagar: ");
                decimal montoMinimo = Convert.ToDecimal(Console.ReadLine());

                bool exito = EmitirLiquidacion(cuenta, periodo, fechaVencimiento, montoTotal, montoMinimo);
                
                if (exito)
                    Console.WriteLine("\n✅ Liquidación emitida con éxito.");
                else
                    Console.WriteLine("\n❌ Hubo un problema al emitir la liquidación.");
            }
            catch (FormatException)
            {
                // Esto evita que la app explote si tipean letras en lugar de números o usan el punto decimal mal
                Console.WriteLine("\n❌ Error: Formato de dato inválido. Revise las fechas y use la coma/punto correcto para los decimales.");
            }
            catch (Exception ex)
            {
                Console.WriteLine("\n❌ Error inesperado: " + ex.Message);
            }

            // ¡ESTA ES LA CLAVE! Pausamos antes de que vuelva al Main y limpie la pantalla
            Console.WriteLine("\nPresione una tecla para volver al menú...");
            Console.ReadKey();
        }

        static void MenuVerDetalleTarjeta()
        {
            Console.Clear();
            Console.WriteLine("--- DETALLE DE TARJETA ---");
            Console.Write("Ingrese el número de cuenta de la tarjeta: ");
            if (int.TryParse(Console.ReadLine(), out int cuenta))
            {
                MostrarDetalleCompleto(cuenta);
            }
            else
            {
                Console.WriteLine("Número de cuenta inválido. Presione una tecla para volver al menú...");
                Console.ReadKey();
            }
        }

        static void MenuEliminarTarjeta()
        {
            Console.Clear();
            Console.WriteLine("--- ELIMINAR TARJETA, CLIENTE Y LIQUIDACIONES ---");
            Console.Write("Ingrese el número de cuenta de la tarjeta a eliminar: ");
            
            if (int.TryParse(Console.ReadLine(), out int cuenta))
            {
                // Un pequeño cartel de advertencia nunca está de más
                Console.WriteLine("\n⚠️  ATENCIÓN: Se eliminará la tarjeta, todas sus liquidaciones y el usuario por completo.");
                Console.Write("¿Desea continuar? (S/N): ");
                if (Console.ReadLine().ToUpper() == "S")
                {
                    bool exito = DarDeBajaTarjeta(cuenta);
                    if (exito)
                        Console.WriteLine("\n✅ Tarjeta, cliente y liquidaciones eliminadas con éxito (Borrado en Cascada).");
                    else
                        Console.WriteLine("\n❌ No se encontró la tarjeta o hubo un problema al eliminarla.");
                }
                else
                {
                    Console.WriteLine("\nOperación cancelada por el usuario.");
                }
            }
            else
            {
                Console.WriteLine("Número de cuenta inválido.");
            }
            
            // Pausa obligatoria para poder leer los mensajes
            Console.WriteLine("\nPresione una tecla para volver al menú...");
            Console.ReadKey();
        }

        static void ObtenerYMostrarTarjetas()
        {
            using (MySqlConnection connection = new MySqlConnection(connectionString))
            {
                try
                {
                    connection.Open();
                    string query = "SELECT num_cuenta, numero_tarjeta, banco_emisor, dni_titular FROM tarjetas";
                    MySqlCommand command = new MySqlCommand(query, connection);
                    MySqlDataReader reader = command.ExecuteReader();

                    while (reader.Read())
                    {
                        string numeroCuenta = reader["num_cuenta"].ToString();
                        string numeroTarjeta = reader["numero_tarjeta"].ToString();
                        string bancoEmisor = reader["banco_emisor"].ToString();
                        string dniTitular = reader["dni_titular"].ToString();

                        Console.WriteLine("{0,-12} {1,-18} {2,-20} {3,-15}", numeroCuenta, numeroTarjeta, bancoEmisor, dniTitular);
                    }
                }
                catch (Exception ex)
                {
                    Console.WriteLine("Error al obtener las tarjetas: " + ex.Message);
                }
            }
        }

        static void MostrarDetalleCompleto(int cuenta)
        {
            using (MySqlConnection connection = new MySqlConnection(connectionString))
            {
                try
                {
                    connection.Open();
                    string query = "SELECT * FROM tarjetas WHERE num_cuenta = @cuenta";
                    MySqlCommand command = new MySqlCommand(query, connection);
                    command.Parameters.AddWithValue("@cuenta", cuenta);
                    MySqlDataReader reader = command.ExecuteReader();

                    if (reader.Read())
                    {
                        Console.WriteLine("\nDetalle de la Tarjeta:");
                        Console.WriteLine("Número de Cuenta: " + reader["num_cuenta"]);
                        Console.WriteLine("Número de Tarjeta: " + reader["numero_tarjeta"]);
                        Console.WriteLine("Banco Emisor: " + reader["banco_emisor"]);
                        Console.WriteLine("DNI del Titular: " + reader["dni_titular"]);
                        // Agregar más campos según sea necesario
                    }
                    else
                    {
                        Console.WriteLine("No se encontró ninguna tarjeta con el número de cuenta proporcionado.");
                    }
                }
                catch (Exception ex)
                {
                    Console.WriteLine("Error al obtener el detalle de la tarjeta: " + ex.Message);
                }
            }

            Console.WriteLine("\nPresione una tecla para volver al menú...");
            Console.ReadKey();
        }

        static bool DarDeBajaTarjeta(int cuenta)
        {
            using (MySqlConnection conexion = new MySqlConnection(connectionString))
            {
                try
                {
                    conexion.Open();

                    // --- PASO 1: Obtener el DNI del titular usando el número de cuenta ---
                    string queryBuscarDni = "SELECT dni_titular FROM tarjetas WHERE num_cuenta = @cuenta";
                    string dniTitular = null;

                    using (MySqlCommand cmdBuscar = new MySqlCommand(queryBuscarDni, conexion))
                    {
                        cmdBuscar.Parameters.AddWithValue("@cuenta", cuenta);
                        
                        // Usamos ExecuteScalar porque solo queremos un dato (el DNI)
                        object resultado = cmdBuscar.ExecuteScalar();
                        
                        if (resultado != null)
                        {
                            dniTitular = resultado.ToString();
                        }
                    }

                    // Si dniTitular sigue siendo null, significa que el número de cuenta no existe.
                    if (string.IsNullOrEmpty(dniTitular))
                    {
                        return false; 
                    }

                    // --- PASO 2: Eliminar al usuario (MySQL elimina la tarjeta y liquidaciones automáticamente) ---
                    string queryEliminar = "DELETE FROM usuarios WHERE documento = @dni";
                    
                    using (MySqlCommand cmdEliminar = new MySqlCommand(queryEliminar, conexion))
                    {
                        cmdEliminar.Parameters.AddWithValue("@dni", dniTitular);
                        
                        // ExecuteNonQuery devuelve la cantidad de filas afectadas.
                        int filasAfectadas = cmdEliminar.ExecuteNonQuery();
                        
                        // Si afectó al menos a 1 fila, la eliminación fue un éxito.
                        return filasAfectadas > 0;
                    }
                }
                catch (MySqlException ex)
                {
                    Console.WriteLine("\n[Error de Base de Datos]: " + ex.Message);
                    return false;
                }
            }
        }

        static bool RegistrarClienteYTarjeta(string documento, string tipoDoc, string nombre, string apellido, DateTime fechaNacimiento, string email, string numeroTarjeta, string bancoEmisor)
        {
            using (MySqlConnection connection = new MySqlConnection(connectionString))
            {
                try
                {
                    connection.Open();

                    // 1. Insertar Cliente
                    string insertClienteQuery = "INSERT INTO usuarios (documento, tipo_doc, nombre, apellido, fecha_nacimiento, email) VALUES (@documento, @tipoDoc, @nombre, @apellido, @fechaNacimiento, @email)";
                    MySqlCommand insertClienteCmd = new MySqlCommand(insertClienteQuery, connection);
                    insertClienteCmd.Parameters.AddWithValue("@documento", documento);
                    insertClienteCmd.Parameters.AddWithValue("@tipoDoc", tipoDoc);
                    insertClienteCmd.Parameters.AddWithValue("@nombre", nombre);
                    insertClienteCmd.Parameters.AddWithValue("@apellido", apellido);
                    insertClienteCmd.Parameters.AddWithValue("@fechaNacimiento", fechaNacimiento);
                    insertClienteCmd.Parameters.AddWithValue("@email", email);
                    insertClienteCmd.ExecuteNonQuery();

                    // Obtener el ID del cliente recién insertado
                    long clienteId = insertClienteCmd.LastInsertedId;

                    // 2. Insertar Tarjeta
                    string insertTarjetaQuery = "INSERT INTO tarjetas (numero_tarjeta, banco_emisor, dni_titular) VALUES (@numeroTarjeta, @bancoEmisor, @dniTitular)";
                    MySqlCommand insertTarjetaCmd = new MySqlCommand(insertTarjetaQuery, connection);
                    insertTarjetaCmd.Parameters.AddWithValue("@numeroTarjeta", numeroTarjeta);
                    insertTarjetaCmd.Parameters.AddWithValue("@bancoEmisor", bancoEmisor);
                    insertTarjetaCmd.Parameters.AddWithValue("@dniTitular", documento); // Asumiendo que el DNI es el documento del cliente
                    insertTarjetaCmd.ExecuteNonQuery();

                    return true; // Registro exitoso
                }
                catch (Exception ex)
                {
                    Console.WriteLine("Error al registrar cliente y tarjeta: " + ex.Message);
                    return false; // Hubo un error
                }
            }
        }

        static bool EmitirLiquidacion(int cuenta, string periodo, DateTime fechaVencimiento, decimal montoTotal, decimal montoMinimo)
        {
            using (MySqlConnection connection = new MySqlConnection(connectionString))
            {
                try
                {
                    connection.Open();

                    string insertLiquidacionQuery = "INSERT INTO liquidaciones (num_cuenta, periodo, fecha_vencimiento, total_a_pagar, pago_minimo) VALUES (@cuenta, @periodo, @fechaVencimiento, @montoTotal, @montoMinimo)";
                    
                    // Envolvemos el comando también en un 'using'
                    using (MySqlCommand insertLiquidacionCmd = new MySqlCommand(insertLiquidacionQuery, connection))
                    {
                        insertLiquidacionCmd.Parameters.AddWithValue("@cuenta", cuenta);
                        insertLiquidacionCmd.Parameters.AddWithValue("@periodo", periodo);
                        
                        // TRUCO: Formateamos la fecha explícitamente a string (yyyy-MM-dd). 
                        // A veces el Connector de MySQL se confunde con los objetos DateTime nativos de C#.
                        insertLiquidacionCmd.Parameters.AddWithValue("@fechaVencimiento", fechaVencimiento.ToString("yyyy-MM-dd"));
                        
                        insertLiquidacionCmd.Parameters.AddWithValue("@montoTotal", montoTotal);
                        insertLiquidacionCmd.Parameters.AddWithValue("@montoMinimo", montoMinimo);
                        
                        insertLiquidacionCmd.ExecuteNonQuery();
                    }

                    return true; 
                }
                catch (MySqlException ex)
                {
                    // Si ponés un número de cuenta que NO EXISTE, la Foreign Key de MySQL 
                    // rechazará el Insert y este Catch atrapará el error mostrándolo.
                    Console.WriteLine("\n[Error de Base de Datos]: " + ex.Message);
                    return false;
                }
            }
        }
    }
}