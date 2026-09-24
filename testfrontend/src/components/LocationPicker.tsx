import { useCallback, useEffect, useRef, useState } from 'react'
import { autocompletePlaces, fetchPlaceDetails, loadGoogleMaps, type PlaceSuggestion } from '../lib/googleMaps'

interface LocationPickerProps {
  address: string
  lat: number | null
  lng: number | null
  onChange: (location: { address: string; lat: number; lng: number }) => void
}

const DEFAULT_CENTER = { lat: 20, lng: 0 }

/**
 * Address search (Places API autocomplete + details) backed by an
 * interactive Google map — click or drag the marker to fine-tune the pin.
 * Replaces manual lat/lng entry (RegisterPage, admin store form).
 */
export function LocationPicker({ address, lat, lng, onChange }: LocationPickerProps) {
  const mapDivRef = useRef<HTMLDivElement | null>(null)
  const mapRef = useRef<google.maps.Map | null>(null)
  const markerRef = useRef<google.maps.Marker | null>(null)
  const onChangeRef = useRef(onChange)
  const queryRef = useRef(address)
  const suppressSearchRef = useRef(false)

  const [query, setQuery] = useState(address)
  const [suggestions, setSuggestions] = useState<PlaceSuggestion[]>([])
  const [mapError, setMapError] = useState<string | null>(null)
  const [ready, setReady] = useState(false)

  useEffect(() => {
    onChangeRef.current = onChange
  }, [onChange])

  useEffect(() => {
    queryRef.current = query
  }, [query])

  const placeMarker = useCallback((position: google.maps.LatLngLiteral) => {
    const map = mapRef.current
    if (!map) {
      return
    }
    if (markerRef.current) {
      markerRef.current.setPosition(position)
    } else {
      const marker = new google.maps.Marker({ position, map, draggable: true })
      marker.addListener('dragend', () => {
        const pos = marker.getPosition()
        if (pos) {
          onChangeRef.current({ address: queryRef.current, lat: pos.lat(), lng: pos.lng() })
        }
      })
      markerRef.current = marker
    }
    map.panTo(position)
  }, [])

  // Init the map once.
  useEffect(() => {
    let cancelled = false
    loadGoogleMaps()
      .then(() => {
        if (cancelled || !mapDivRef.current) {
          return
        }
        const center = lat !== null && lng !== null ? { lat, lng } : DEFAULT_CENTER
        const map = new google.maps.Map(mapDivRef.current, {
          center,
          zoom: lat !== null && lng !== null ? 13 : 2,
        })
        mapRef.current = map
        if (lat !== null && lng !== null) {
          placeMarker({ lat, lng })
        }
        map.addListener('click', (event: google.maps.MapMouseEvent) => {
          if (!event.latLng) {
            return
          }
          const position = { lat: event.latLng.lat(), lng: event.latLng.lng() }
          placeMarker(position)
          onChangeRef.current({ address: queryRef.current, lat: position.lat, lng: position.lng })
        })
        setReady(true)
      })
      .catch((err) => setMapError(err instanceof Error ? err.message : 'Failed to load map'))
    return () => {
      cancelled = true
    }
  }, [placeMarker, lat, lng])

  // Keep the pin in sync when the parent moves lat/lng externally (e.g. a picked suggestion).
  useEffect(() => {
    if (!ready || lat === null || lng === null) {
      return
    }
    placeMarker({ lat, lng })
  }, [ready, lat, lng, placeMarker])

  // Debounced address autocomplete.
  useEffect(() => {
    if (suppressSearchRef.current) {
      suppressSearchRef.current = false
      return
    }
    const trimmed = query.trim()
    const handle = setTimeout(() => {
      if (trimmed.length < 3) {
        setSuggestions([])
        return
      }
      autocompletePlaces(query).then(setSuggestions).catch(() => setSuggestions([]))
    }, 300)
    return () => clearTimeout(handle)
  }, [query])

  async function selectSuggestion(suggestion: PlaceSuggestion) {
    suppressSearchRef.current = true
    setQuery(suggestion.text)
    setSuggestions([])
    const details = await fetchPlaceDetails(suggestion.placeId)
    if (details) {
      onChange(details)
    }
  }

  return (
    <div className="location-picker">
      <div className="form-row" style={{ position: 'relative' }}>
        <label htmlFor="location-search">Search address</label>
        <input
          id="location-search"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder="Start typing an address…"
          autoComplete="off"
        />
        {suggestions.length > 0 && (
          <ul className="location-suggestions">
            {suggestions.map((suggestion) => (
              <li key={suggestion.placeId}>
                <button type="button" onClick={() => selectSuggestion(suggestion)}>
                  {suggestion.text}
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>

      {mapError ? (
        <p className="error-banner">Map unavailable ({mapError}). Click the address search above, or contact support.</p>
      ) : (
        <div ref={mapDivRef} className="location-map" />
      )}

      {lat !== null && lng !== null && (
        <p className="location-coords">
          Selected: {lat.toFixed(6)}, {lng.toFixed(6)} — click the map or drag the pin to adjust
        </p>
      )}
    </div>
  )
}
