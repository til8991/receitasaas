import { useRef, useState, type DragEvent } from 'react'

/** Drag-and-drop area for XML/ZIP files, also usable with keyboard (opens the file picker). */
export function Dropzone({ onFiles, disabled }: { onFiles: (files: File[]) => void; disabled?: boolean }) {
  const [over, setOver] = useState(false)
  const input = useRef<HTMLInputElement>(null)

  const accept = (list: FileList | null) => {
    const files = Array.from(list ?? []).filter((f) => /\.(xml|zip)$/i.test(f.name))
    if (files.length) onFiles(files)
  }

  const onDrop = (e: DragEvent) => {
    e.preventDefault()
    setOver(false)
    if (!disabled) accept(e.dataTransfer.files)
  }

  return (
    <div
      role="button"
      tabIndex={disabled ? -1 : 0}
      aria-disabled={disabled}
      onClick={() => !disabled && input.current?.click()}
      onKeyDown={(e) => { if ((e.key === 'Enter' || e.key === ' ') && !disabled) { e.preventDefault(); input.current?.click() } }}
      onDragOver={(e) => { e.preventDefault(); if (!disabled) setOver(true) }}
      onDragLeave={() => setOver(false)}
      onDrop={onDrop}
      className={`flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed px-6 py-10 text-center transition-colors
        ${over ? 'border-brand bg-brand/5' : 'border-rule bg-sheet hover:border-brand/60'} ${disabled ? 'cursor-not-allowed opacity-60' : ''}`}
    >
      <svg viewBox="0 0 24 24" className="mb-3 h-8 w-8 text-brand" fill="none" stroke="currentColor" strokeWidth="1.6" aria-hidden>
        <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z" /><path d="M14 3v5h5M12 18v-6M9 15l3-3 3 3" />
      </svg>
      <p className="font-semibold">Arraste os XMLs ou um arquivo .zip para cá</p>
      <p className="mt-1 text-sm text-muted">NF-e, NFC-e e NFS-e (nacional ou ABRASF). Ou clique para escolher os arquivos.</p>
      <input
        ref={input}
        type="file"
        multiple
        accept=".xml,.zip,application/xml,text/xml,application/zip"
        className="hidden"
        onChange={(e) => { accept(e.target.files); e.target.value = '' }}
      />
    </div>
  )
}
