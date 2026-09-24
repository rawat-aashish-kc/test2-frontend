const MAP_API_KEY = import.meta.env.VITE_MAP_API_KEY as string | undefined
export const AUTOCOMPLETE_URL = import.meta.env.VITE_GOOGLE_AUTOCOMPLETE as string | undefined
export const PLACE_DETAIL_BASE = import.meta.env.VITE_GOOGLE_PLACE_DETAIL_BASE as string | undefined

let loadPromise: Promise<typeof google.maps> | null = null

/**
 * Loads the Google Maps JavaScript API once and caches the promise, so every
 * LocationPicker instance on the page shares a single script tag/load.
 */
export function loadGoogleMaps(): Promise<typeof google.maps> {
  if (loadPromise) {
    return loadPromise
  }

  if (!MAP_API_KEY) {
    return Promise.reject(new Error('VITE_MAP_API_KEY is not set'))
  }

  loadPromise = new Promise((resolve, reject) => {
    if (window.google?.maps?.Map) {
      resolve(window.google.maps)
      return
    }

    const callbackName = '__onGoogleMapsLoaded'
    ;(window as unknown as Record<string, () => void>)[callbackName] = () => resolve(window.google.maps)

    if (document.querySelector('script[data-google-maps]')) {
      return
    }

    const script = document.createElement('script')
    script.src = `https://maps.googleapis.com/maps/api/js?key=${MAP_API_KEY}&callback=${callbackName}`
    script.async = true
    script.dataset.googleMaps = 'true'
    script.onerror = () => reject(new Error('Failed to load Google Maps'))
    document.head.appendChild(script)
  })

  return loadPromise
}

export interface PlaceSuggestion {
  placeId: string
  text: string
}

/**
 * Places API (New) Autocomplete — text search, no map needed for this call.
 */
export async function autocompletePlaces(input: string): Promise<PlaceSuggestion[]> {
  if (!AUTOCOMPLETE_URL || !MAP_API_KEY || input.trim().length < 3) {
    return []
  }

  const response = await fetch(AUTOCOMPLETE_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Goog-Api-Key': MAP_API_KEY },
    body: JSON.stringify({ input }),
  })
  if (!response.ok) {
    return []
  }

  const body = await response.json()
  const suggestions = (body.suggestions ?? []) as Array<{
    placePrediction?: { placeId: string; text?: { text: string } }
  }>

  return suggestions
    .filter((s) => s.placePrediction)
    .map((s) => ({
      placeId: s.placePrediction!.placeId,
      text: s.placePrediction!.text?.text ?? '',
    }))
}

/**
 * Places API (New) Place Details — resolves a placeId into an address + lat/lng.
 */
export async function fetchPlaceDetails(placeId: string): Promise<{ address: string; lat: number; lng: number } | null> {
  if (!PLACE_DETAIL_BASE || !MAP_API_KEY) {
    return null
  }

  const response = await fetch(`${PLACE_DETAIL_BASE}/${placeId}`, {
    headers: { 'X-Goog-Api-Key': MAP_API_KEY, 'X-Goog-FieldMask': 'id,formattedAddress,location' },
  })
  if (!response.ok) {
    return null
  }

  const body = await response.json()
  if (!body.location) {
    return null
  }

  return {
    address: body.formattedAddress ?? '',
    lat: body.location.latitude,
    lng: body.location.longitude,
  }
}
