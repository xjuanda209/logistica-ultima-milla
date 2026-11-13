import { Camera } from '@capacitor/camera';
import { Geolocation } from '@capacitor/geolocation';

/**
 * Cámara, galería y ubicación del teléfono (plugins de Capacitor). En el navegador (ionic serve)
 * la cámara y la galería abren el selector de archivos y la ubicación usa la del navegador.
 *
 *   const foto = await tomarFoto();            // o elegirDeGaleria()
 *   if (foto && !foto.error) { formulario.append('archivo', foto.archivo, foto.nombre); }
 *   const ubicacion = await obtenerUbicacion(); // null si no hay permiso
 */

/** Lo mismo que valida el backend: JPG o PNG de máximo 5 MB. */
export const MENSAJE_IMAGEN_INVALIDA = 'La imagen debe ser JPG o PNG y pesar 5 MB o menos.';
const TAMANO_MAXIMO = 5 * 1024 * 1024;
const FORMATOS: Record<string, string> = { 'image/jpeg': 'JPG', 'image/png': 'PNG' };

export interface Foto {
  archivo: Blob;
  /** Nombre con el que se envía a la API. */
  nombre: string;
  /** Dirección para mostrarla en un <img>. */
  vistaPrevia: string;
  /** "2,4 MB · JPG" */
  descripcion: string;
  /** Mensaje si la imagen no cumple el formato o el tamaño; en ese caso no se debe enviar. */
  error: string | null;
}

export interface Ubicacion {
  latitud: number;
  longitud: number;
}

// Calidad y tamaño reducidos: una foto de entrega no necesita más y sube rápido con datos móviles.
const OPCIONES_IMAGEN = { quality: 70, targetWidth: 1600, targetHeight: 1600, webUseInput: true };

/** Abre la cámara. Devuelve null si la persona cancela o no da permiso. */
export async function tomarFoto(): Promise<Foto | null> {
  try {
    const resultado = await Camera.takePhoto(OPCIONES_IMAGEN);

    return await leerFoto(resultado.webPath);
  } catch {
    return null;
  }
}

/** Abre la galería. Devuelve null si la persona cancela o no da permiso. */
export async function elegirDeGaleria(): Promise<Foto | null> {
  try {
    const { results } = await Camera.chooseFromGallery({ ...OPCIONES_IMAGEN, allowMultipleSelection: false, limit: 1 });

    return await leerFoto(results[0]?.webPath);
  } catch {
    return null;
  }
}

/** Ubicación actual, o null si no hay permiso o el teléfono no la entrega a tiempo. */
export async function obtenerUbicacion(): Promise<Ubicacion | null> {
  try {
    const posicion = await Geolocation.getCurrentPosition({ enableHighAccuracy: true, timeout: 10000 });

    return {
      latitud: Number(posicion.coords.latitude.toFixed(6)),
      longitud: Number(posicion.coords.longitude.toFixed(6)),
    };
  } catch {
    return null;
  }
}

async function leerFoto(ruta: string | undefined): Promise<Foto | null> {
  if (!ruta) {
    return null;
  }

  const archivo = await (await fetch(ruta)).blob();
  const formato = FORMATOS[archivo.type];
  const megas = (archivo.size / (1024 * 1024)).toFixed(1).replace('.', ',');

  return {
    archivo,
    nombre: `foto.${formato === 'PNG' ? 'png' : 'jpg'}`,
    vistaPrevia: ruta,
    descripcion: `${megas} MB · ${formato ?? 'formato no permitido'}`,
    error: formato && archivo.size <= TAMANO_MAXIMO ? null : MENSAJE_IMAGEN_INVALIDA,
  };
}
