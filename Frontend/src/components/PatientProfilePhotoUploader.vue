<template>
  <input
    ref="fileInput"
    class="profile-uploader-input"
    type="file"
    accept="image/*"
    tabindex="-1"
    aria-hidden="true"
    @change="onFileSelected"
  >

  <Teleport to="body">
    <div v-if="crop.open" class="profile-crop-overlay" @click.self="cancel">
      <section class="profile-crop-modal" dir="rtl" role="dialog" aria-modal="true" aria-labelledby="profile-uploader-title" @click.stop>
        <header class="profile-crop-header">
          <div>
            <h3 id="profile-uploader-title">تنظیم عکس پروفایل</h3>
            <p>صورت را داخل محدوده دایره‌ای قرار دهید</p>
          </div>
          <button type="button" class="profile-crop-close" title="بستن" aria-label="بستن" :disabled="uploading" @click="cancel">×</button>
        </header>

        <div
          ref="stage"
          class="profile-crop-stage"
          :class="{ dragging: crop.dragging }"
          @pointerdown="startDrag"
          @pointermove="moveDrag"
          @pointerup="endDrag"
          @pointercancel="endDrag"
        >
          <img
            v-if="crop.sourceUrl"
            :src="crop.sourceUrl"
            alt="پیش‌نمایش عکس بیمار"
            :class="{ portrait: crop.image && crop.image.naturalHeight > crop.image.naturalWidth }"
            :style="{ transform: `translate(calc(-50% + ${crop.x}px), calc(-50% + ${crop.y}px)) scale(${crop.zoom})` }"
            draggable="false"
          >
          <div class="profile-face-guide" aria-hidden="true">
            <span class="face-head"></span>
            <span class="face-shoulders"></span>
          </div>
        </div>

        <p class="profile-crop-help">عکس را بکشید تا چهره وسط قرار بگیرد. بهتر است سر و شانه‌ها داخل دایره باشند.</p>
        <label class="profile-crop-zoom">
          <span>کوچک</span>
          <input v-model.number="crop.zoom" type="range" min="1" max="3" step="0.01" @input="clampPosition">
          <span>بزرگ</span>
        </label>
        <p v-if="error" class="profile-crop-error">{{ error }}</p>
        <div class="profile-crop-actions">
          <button type="button" class="secondary-btn" :disabled="uploading" @click="cancel">انصراف</button>
          <button type="button" class="primary-btn" :disabled="uploading" @click="confirm">
            {{ uploading ? 'در حال ذخیره...' : 'تأیید و ذخیره عکس' }}
          </button>
        </div>
      </section>
    </div>
  </Teleport>
</template>

<script>
// Picks, crops and uploads a patient's profile photo (same output as the
// patient-file gallery: an 800px photo plus a 50px thumbnail).
const emptyCrop = () => ({
  open: false,
  sourceUrl: '',
  image: null,
  zoom: 1,
  x: 0,
  y: 0,
  dragging: false,
  pointerX: 0,
  pointerY: 0
})

export default {
  name: 'PatientProfilePhotoUploader',
  props: {
    patientId: { type: [Number, String], default: null }
  },
  emits: ['uploaded', 'error'],
  data: () => ({
    crop: emptyCrop(),
    uploading: false,
    error: ''
  }),
  beforeUnmount() {
    if (this.crop.sourceUrl) URL.revokeObjectURL(this.crop.sourceUrl)
  },
  methods: {
    // Must be called from a click handler so the browser opens the file dialog.
    pick() {
      if (!this.patientId || this.uploading) return
      this.$refs.fileInput?.click()
    },
    async onFileSelected(event) {
      const file = (event.target.files || [])[0]
      event.target.value = ''
      if (!file) return
      if (!file.type.startsWith('image/')) {
        this.$emit('error', 'لطفاً یک فایل تصویری انتخاب کنید.')
        return
      }

      const sourceUrl = URL.createObjectURL(file)
      try {
        const image = await new Promise((resolve, reject) => {
          const element = new Image()
          element.onload = () => resolve(element)
          element.onerror = () => reject(new Error('خواندن تصویر انتخاب‌شده ممکن نیست.'))
          element.src = sourceUrl
        })
        this.error = ''
        this.crop = { ...emptyCrop(), open: true, sourceUrl, image }
      } catch (error) {
        URL.revokeObjectURL(sourceUrl)
        this.$emit('error', error.message)
      }
    },
    cancel() {
      if (this.uploading) return
      if (this.crop.sourceUrl) URL.revokeObjectURL(this.crop.sourceUrl)
      this.crop = emptyCrop()
      this.error = ''
    },
    frameSize() {
      return this.$refs.stage?.clientWidth || 320
    },
    startDrag(event) {
      if (!this.crop.image) return
      event.currentTarget.setPointerCapture?.(event.pointerId)
      this.crop.dragging = true
      this.crop.pointerX = event.clientX
      this.crop.pointerY = event.clientY
    },
    moveDrag(event) {
      if (!this.crop.dragging) return
      this.crop.x += event.clientX - this.crop.pointerX
      this.crop.y += event.clientY - this.crop.pointerY
      this.crop.pointerX = event.clientX
      this.crop.pointerY = event.clientY
      this.clampPosition()
    },
    endDrag() {
      this.crop.dragging = false
    },
    clampPosition() {
      const image = this.crop.image
      if (!image) return
      const frame = this.frameSize()
      const baseScale = Math.max(frame / image.naturalWidth, frame / image.naturalHeight)
      const scale = baseScale * Number(this.crop.zoom || 1)
      const maxX = Math.max(0, (image.naturalWidth * scale - frame) / 2)
      const maxY = Math.max(0, (image.naturalHeight * scale - frame) / 2)
      this.crop.x = Math.max(-maxX, Math.min(maxX, this.crop.x))
      this.crop.y = Math.max(-maxY, Math.min(maxY, this.crop.y))
    },
    async createCroppedFile(size, quality, filename) {
      const image = this.crop.image
      if (!image) throw new Error('تصویری برای برش انتخاب نشده است.')

      const frame = this.frameSize()
      const baseScale = Math.max(frame / image.naturalWidth, frame / image.naturalHeight)
      const previewScale = baseScale * Number(this.crop.zoom || 1)
      const outputScale = size / frame

      const canvas = document.createElement('canvas')
      canvas.width = size
      canvas.height = size
      const context = canvas.getContext('2d')
      context.imageSmoothingEnabled = true
      context.imageSmoothingQuality = 'high'
      context.setTransform(
        previewScale * outputScale,
        0,
        0,
        previewScale * outputScale,
        size / 2 + this.crop.x * outputScale,
        size / 2 + this.crop.y * outputScale
      )
      context.drawImage(image, -image.naturalWidth / 2, -image.naturalHeight / 2)
      let blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/webp', quality))
      // Safari may silently return PNG when WebP encoding is unavailable.
      if (blob?.type !== 'image/webp') blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', quality))
      if (!blob) throw new Error('ساخت تصویر برش‌خورده ممکن نیست.')
      const extension = blob.type === 'image/webp' ? 'webp' : blob.type === 'image/jpeg' ? 'jpg' : 'png'
      return new File([blob], filename.replace(/\.[^.]+$/, `.${extension}`), { type: blob.type })
    },
    async confirm() {
      if (!this.patientId || this.uploading) return
      this.uploading = true
      this.error = ''
      try {
        const formData = new FormData()
        formData.append('photo', await this.createCroppedFile(800, 0.86, 'profile.webp'))
        formData.append('thumbnail', await this.createCroppedFile(50, 0.48, 'thumbnail.webp'))

        const res = await fetch(`/api/patients/${this.patientId}/profile-photo`, {
          method: 'POST',
          headers: { Accept: 'application/json' },
          body: formData
        })
        const data = await res.json().catch(() => ({}))
        if (!res.ok) throw new Error(data.message || 'عکس پروفایل ذخیره نشد.')

        this.uploading = false
        this.cancel()
        this.$emit('uploaded', data)
      } catch (error) {
        this.error = error.message || 'عکس پروفایل ذخیره نشد.'
      } finally {
        this.uploading = false
      }
    }
  }
}
</script>

<style scoped>
.profile-uploader-input {
  display: none;
}
.profile-crop-overlay {
  position: fixed;
  z-index: 2147483647;
  inset: 0;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgba(15, 23, 42, .78);
  backdrop-filter: blur(8px);
  font-family: "Vazir", Tahoma, sans-serif;
}
.profile-crop-modal {
  width: min(470px, 100%);
  box-sizing: border-box;
  padding: 20px;
  border-radius: 22px;
  background: #fff;
  box-shadow: 0 30px 80px rgba(0, 0, 0, .32);
}
.profile-crop-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; margin-bottom: 16px; }
.profile-crop-header h3 { margin: 0 0 5px; color: #0f172a; font-size: 18px; }
.profile-crop-header p { margin: 0; color: #64748b; font-size: 11px; }
.profile-crop-close { width: 40px; height: 40px; border: 0; border-radius: 11px; background: #f1f5f9; color: #475569; font-size: 25px; cursor: pointer; }
.profile-crop-stage {
  position: relative;
  width: min(320px, 78vw);
  aspect-ratio: 1;
  margin: 0 auto;
  overflow: hidden;
  border: 2px solid #fff;
  border-radius: 50%;
  box-shadow: 0 0 0 1px rgba(15, 23, 42, .12);
  background: #111827;
  touch-action: none;
  cursor: grab;
  user-select: none;
}
.profile-crop-stage.dragging { cursor: grabbing; }
.profile-crop-stage > img { position: absolute; top: 50%; left: 50%; height: 100%; width: auto; max-width: none; pointer-events: none; transform-origin: center; }
.profile-crop-stage > img.portrait { width: 100%; height: auto; }
.profile-face-guide { position: absolute; inset: 0; opacity: .34; pointer-events: none; }
.face-head { position: absolute; top: 20%; left: 38%; width: 24%; height: 31%; border: 1px dashed #fff; border-radius: 48%; }
.face-shoulders { position: absolute; left: 24%; bottom: 14%; width: 52%; height: 29%; border: 1px dashed #fff; border-bottom: 0; border-radius: 50% 50% 0 0; }
.profile-crop-help { margin: 13px 0 10px; color: #64748b; font-size: 11px; line-height: 1.8; text-align: center; }
.profile-crop-zoom { display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 10px; color: #64748b; font-size: 10px; }
.profile-crop-zoom input { width: 100%; accent-color: #2563eb; }
.profile-crop-error { margin: 12px 0 0; padding: 9px; border-radius: 9px; background: #fef2f2; color: #b91c1c; font-size: 11px; }
.profile-crop-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 18px; }
.profile-crop-actions button { min-height: 40px; border: 0; border-radius: 11px; padding: 0 16px; font-family: inherit; font-size: 11px; font-weight: 800; cursor: pointer; }
.profile-crop-actions .primary-btn { background: #2563eb; color: #fff; }
.profile-crop-actions .secondary-btn { background: #eaf2ff; color: #1d4ed8; }
.profile-crop-actions button:disabled { opacity: .55; cursor: wait; }
</style>
