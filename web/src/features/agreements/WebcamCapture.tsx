import { useEffect, useRef, useState } from 'react'

/**
 * Photographs the client at the moment of signing.
 *
 * The stream is stopped the instant a frame is taken or the component goes
 * away - a camera left running because a page was navigated away from is the
 * kind of thing people rightly complain about.
 */
export function WebcamCapture({
  photo,
  onChange,
}: {
  photo: string | null
  onChange: (dataUrl: string | null) => void
}) {
  const videoRef = useRef<HTMLVideoElement>(null)
  const streamRef = useRef<MediaStream | null>(null)
  const [isLive, setIsLive] = useState(false)
  const [error, setError] = useState<string | null>(null)

  function stop() {
    streamRef.current?.getTracks().forEach((track) => track.stop())
    streamRef.current = null
    setIsLive(false)
  }

  useEffect(() => stop, [])

  async function start() {
    setError(null)

    if (!navigator.mediaDevices?.getUserMedia) {
      setError('This browser will not give the page a camera.')

      return
    }

    try {
      const stream = await navigator.mediaDevices.getUserMedia({
        video: { width: 640, height: 480, facingMode: 'user' },
        audio: false,
      })

      streamRef.current = stream

      if (videoRef.current) {
        videoRef.current.srcObject = stream
        await videoRef.current.play()
      }

      setIsLive(true)
    } catch {
      // Permission refused, or no camera attached. Neither is fatal - the
      // photograph is corroborating evidence, not the signature itself.
      setError('The camera could not be started. Check the browser permission, or skip the photo.')
    }
  }

  function capture() {
    const video = videoRef.current

    if (!video) {
      return
    }

    const canvas = document.createElement('canvas')
    canvas.width = video.videoWidth
    canvas.height = video.videoHeight
    canvas.getContext('2d')?.drawImage(video, 0, 0)

    onChange(canvas.toDataURL('image/jpeg', 0.85))
    stop()
  }

  if (photo) {
    return (
      <div>
        <img
          src={photo}
          alt="Captured at signing"
          className="w-full rounded-md border border-slate-300"
        />
        <button
          type="button"
          onClick={() => {
            onChange(null)
            void start()
          }}
          className="mt-2 text-xs font-medium text-slate-500 hover:text-brand-700"
        >
          Retake
        </button>
      </div>
    )
  }

  return (
    <div>
      <div className="relative overflow-hidden rounded-md border border-slate-300 bg-slate-900">
        <video
          ref={videoRef}
          playsInline
          muted
          className={`aspect-[4/3] w-full object-cover ${isLive ? '' : 'hidden'}`}
        />

        {!isLive && (
          <div className="flex aspect-[4/3] w-full items-center justify-center">
            <p className="px-4 text-center text-sm text-slate-400">
              The camera is off. Nothing is recorded until you start it.
            </p>
          </div>
        )}
      </div>

      {error && <p className="mt-2 text-xs text-amber-700">{error}</p>}

      <div className="mt-2 flex gap-2">
        {isLive ? (
          <>
            <button
              type="button"
              onClick={capture}
              className="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700"
            >
              Take photo
            </button>
            <button
              type="button"
              onClick={stop}
              className="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100"
            >
              Turn camera off
            </button>
          </>
        ) : (
          <button
            type="button"
            onClick={() => void start()}
            className="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100"
          >
            Start camera
          </button>
        )}
      </div>
    </div>
  )
}
