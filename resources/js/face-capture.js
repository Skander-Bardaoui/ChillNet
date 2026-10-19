/**
 * Composant Alpine de capture webcam pour la connexion / l'enrôlement
 * par reconnaissance faciale.
 *
 * La capture est un JPEG 640×480 (qualité 0.85) converti en data URL ;
 * elle est envoyée au serveur qui la ré-encode puis l'envoie à Face++.
 * Les flux caméra sont toujours coupés après capture ou démontage.
 */
export default function faceCapture() {
    return {
        streaming: false,
        starting: false,
        captured: '',
        cameraError: '',

        init() {
            this.videoEl = this.$refs.video ?? null;
            this.canvasEl = this.$refs.canvas ?? null;
        },

        get supported() {
            return Boolean(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
        },

        get secure() {
            return window.isSecureContext;
        },

        async start() {
            this.cameraError = '';

            if (! this.supported) {
                this.cameraError = "La caméra n'est pas disponible dans ce navigateur. Utilisez votre mot de passe.";
                return;
            }

            if (! window.isSecureContext) {
                this.cameraError = 'La caméra nécessite une connexion sécurisée (HTTPS ou localhost). Utilisez votre mot de passe.';
                return;
            }

            this.starting = true;

            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: 'user',
                        width: { ideal: 640 },
                        height: { ideal: 480 },
                    },
                    audio: false,
                });

                this.videoEl.srcObject = stream;
                await this.videoEl.play().catch(() => {});
                this.streaming = true;
            } catch (error) {
                this.cameraError = this.errorMessage(error);
            } finally {
                this.starting = false;
            }
        },

        capture() {
            const video = this.videoEl;
            const canvas = this.canvasEl;

            if (! video || ! canvas) {
                return;
            }

            const width = 640;
            const height = 480;
            const videoWidth = video.videoWidth || width;
            const videoHeight = video.videoHeight || height;
            const targetRatio = width / height;

            // Recadrage centré au format 4:3 : le visage reste centré quelle
            // que soit la résolution réellement fournie par la caméra.
            let sourceWidth = videoWidth;
            let sourceHeight = videoHeight;

            if (videoWidth / videoHeight > targetRatio) {
                sourceWidth = videoHeight * targetRatio;
            } else {
                sourceHeight = videoWidth / targetRatio;
            }

            const sourceX = (videoWidth - sourceWidth) / 2;
            const sourceY = (videoHeight - sourceHeight) / 2;

            canvas.width = width;
            canvas.height = height;

            const context = canvas.getContext('2d');
            context.drawImage(
                video,
                sourceX,
                sourceY,
                sourceWidth,
                sourceHeight,
                0,
                0,
                width,
                height,
            );

            this.captured = canvas.toDataURL('image/jpeg', 0.85);
            this.stop();
        },

        stop() {
            const stream = this.videoEl?.srcObject;

            if (stream) {
                stream.getTracks().forEach((track) => track.stop());
                this.videoEl.srcObject = null;
            }

            this.streaming = false;
        },

        retake() {
            this.captured = '';
            this.cameraError = '';
            this.start();
        },

        validate() {
            return this.captured !== '';
        },

        destroy() {
            this.stop();
        },

        errorMessage(error) {
            switch (error?.name) {
                case 'NotAllowedError':
                case 'SecurityError':
                    return "L'accès à la caméra a été refusé. Autorisez la caméra dans votre navigateur ou utilisez votre mot de passe.";
                case 'NotFoundError':
                    return 'Aucune caméra n’a été détectée sur cet appareil. Utilisez votre mot de passe.';
                case 'OverconstrainedError':
                case 'ConstraintNotSatisfiedError':
                    return 'La caméra disponible ne permet pas de capturer votre visage. Utilisez votre mot de passe.';
                case 'NotReadableError':
                    return 'La caméra est déjà utilisée par une autre application. Fermez-la puis réessayez.';
                default:
                    return 'Impossible de démarrer la caméra. Utilisez votre mot de passe.';
            }
        },
    };
}
