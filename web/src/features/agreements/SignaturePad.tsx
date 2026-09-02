import { useEffect, useRef, useState } from 'react'

/**
 * A signature drawn with a finger, stylus or mouse.
 *
 * Pointer events rather than mouse events, so a tablet at the desk works the
 * same as a laptop - which is how a client actually signs.
 */
export function SignaturePad({
  onChange,
}: {
  onChange: (dataUrl: string | null) => void
}) {
  const canvasRef = useRef<HTMLCanvasElement>(null)
  const drawing = useRef(false)
  const [hasInk, setHasInk] = useState(false)

  useEffect(() => {
    const canvas = canvasRef.current

    if (!canvas) {
      return
    }

    // Backing store scaled to the device so the signature is not a blurry
    // upscale on a high-DPI screen.
    const ratio = window.devicePixelRatio || 1
    const rect = canvas.getBoundingClientRect()
    canvas.width = rect.width * ratio
    canvas.height = rect.height * ratio

    const context = canvas.getContext('2d')

    if (!context) {
      return
    }

    context.scale(ratio, ratio)
    context.lineWidth = 2
    context.lineCap = 'round'
    context.lineJoin = 'round'
    context.strokeStyle = '#0f172a'
    context.fillStyle = '#ffffff'
    context.fillRect(0, 0, rect.width, rect.height)
  }, [])

  function positionOf(event: React.PointerEvent<HTMLCanvasElement>) {
    const rect = event.currentTarget.getBoundingClientRect()

    return { x: event.clientX - rect.left, y: event.clientY - rect.top }
  }

  function start(event: React.PointerEvent<HTMLCanvasElement>) {
    const context = canvasRef.current?.getContext('2d')

    if (!context) {
      return
    }

    event.currentTarget.setPointerCapture(event.pointerId)
    drawing.current = true

    const { x, y } = positionOf(event)
    context.beginPath()
    context.moveTo(x, y)
  }

  function move(event: React.PointerEvent<HTMLCanvasElement>) {
    if (!drawing.current) {
      return
    }

    const context = canvasRef.current?.getContext('2d')

    if (!context) {
      return
    }

    const { x, y } = positionOf(event)
    context.lineTo(x, y)
    context.stroke()

    if (!hasInk) {
      setHasInk(true)
    }
  }

  function end() {
    if (!drawing.current) {
      return
    }

    drawing.current = false

    const canvas = canvasRef.current

    if (canvas && hasInk) {
      onChange(canvas.toDataURL('image/png'))
    }
  }

  function clear() {
    const canvas = canvasRef.current
    const context = canvas?.getContext('2d')

    if (!canvas || !context) {
      return
    }

    const rect = canvas.getBoundingClientRect()
    context.fillStyle = '#ffffff'
    context.fillRect(0, 0, rect.width, rect.height)
    setHasInk(false)
    onChange(null)
  }

  return (
    <div>
      <canvas
        ref={canvasRef}
        onPointerDown={start}
        onPointerMove={move}
        onPointerUp={end}
        onPointerLeave={end}
        className="h-40 w-full cursor-crosshair touch-none rounded-md border border-slate-300 bg-white"
      />
      <div className="mt-2 flex items-center justify-between">
        <p className="text-xs text-slate-500">
          {hasInk ? 'Signed.' : 'The client signs here, using a finger, stylus or mouse.'}
        </p>
        <button
          type="button"
          onClick={clear}
          className="text-xs font-medium text-slate-500 hover:text-red-600"
        >
          Clear
        </button>
      </div>
    </div>
  )
}
