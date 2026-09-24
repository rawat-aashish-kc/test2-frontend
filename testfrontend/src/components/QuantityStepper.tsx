interface QuantityStepperProps {
  value: number
  onChange: (value: number) => void
  min?: number
  max?: number
  disabled?: boolean
}

export function QuantityStepper({ value, onChange, min = 0, max, disabled }: QuantityStepperProps) {
  function clamp(n: number): number {
    let result = Number.isFinite(n) ? n : min
    if (result < min) result = min
    if (max !== undefined && result > max) result = max
    return result
  }

  return (
    <div className="qty-stepper">
      <button
        type="button"
        className="qty-stepper-btn"
        aria-label="Decrease quantity"
        onClick={() => onChange(clamp(value - 1))}
        disabled={disabled || value <= min}
      >
        −
      </button>
      <input
        type="number"
        className="qty-stepper-input"
        value={value}
        min={min}
        max={max}
        disabled={disabled}
        onChange={(e) => onChange(clamp(Number(e.target.value)))}
      />
      <button
        type="button"
        className="qty-stepper-btn"
        aria-label="Increase quantity"
        onClick={() => onChange(clamp(value + 1))}
        disabled={disabled || (max !== undefined && value >= max)}
      >
        +
      </button>
    </div>
  )
}
