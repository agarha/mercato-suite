{{- define "mercato.name" -}}
mercato-suite
{{- end -}}

{{- define "mercato.labels" -}}
app.kubernetes.io/name: {{ include "mercato.name" . }}
app.kubernetes.io/version: {{ .Chart.AppVersion | quote }}
app.kubernetes.io/managed-by: {{ .Release.Service }}
app.kubernetes.io/part-of: mercato
environment: {{ .Values.global.environment | quote }}
{{- end -}}

{{- define "mercato.selectorLabels" -}}
app.kubernetes.io/name: {{ include "mercato.name" . }}
app.kubernetes.io/part-of: mercato
{{- end -}}
