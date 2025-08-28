"""
Advanced OSINT Dorking Templates
Based on ExampleArrayPythonOSINT structure for comprehensive OSINT analysis
"""

# Dorking templates for alias/username analysis
alias_dorking_templates = [
    # =========================================================================
    # Categoría: Redes Sociales Principales
    # =========================================================================
    {
        "category": "Redes Sociales",
        "objective": "Perfil en Twitter/X",
        "dork": "site:x.com \"{target_alias}\"",
        "description": "Busca el perfil específico del usuario en la plataforma X (anteriormente Twitter)",
        "priority": "high"
    },
    {
        "category": "Redes Sociales",
        "objective": "Menciones en Twitter/X",
        "dork": "site:x.com \"{target_alias}\" OR \"@{target_alias}\"",
        "description": "Encuentra menciones del alias en tweets o conversaciones",
        "priority": "high"
    },
    {
        "category": "Redes Sociales",
        "objective": "Perfil en Facebook",
        "dork": "site:facebook.com \"{target_alias}\"",
        "description": "Localiza perfiles o menciones en Facebook",
        "priority": "high"
    },
    {
        "category": "Redes Sociales",
        "objective": "Actividad en Reddit",
        "dork": "site:reddit.com \"{target_alias}\" OR \"u/{target_alias}\"",
        "description": "Busca el usuario o menciones en Reddit",
        "priority": "high"
    },
    {
        "category": "Redes Sociales",
        "objective": "Perfil en Instagram",
        "dork": "site:instagram.com \"{target_alias}\"",
        "description": "Encuentra el perfil de Instagram asociado",
        "priority": "medium"
    },
    {
        "category": "Redes Sociales",
        "objective": "Perfil profesional en LinkedIn",
        "dork": "site:linkedin.com \"{target_alias}\"",
        "description": "Busca perfiles profesionales en LinkedIn",
        "priority": "high"
    },
    
    # =========================================================================
    # Categoría: Plataformas de Desarrollo y Tecnología
    # =========================================================================
    {
        "category": "Desarrollo",
        "objective": "Repositorios en GitHub",
        "dork": "site:github.com \"{target_alias}\"",
        "description": "Encuentra repositorios y actividad de desarrollo",
        "priority": "high"
    },
    {
        "category": "Desarrollo",
        "objective": "Actividad en Stack Overflow",
        "dork": "site:stackoverflow.com \"{target_alias}\"",
        "description": "Busca preguntas, respuestas o perfil en Stack Overflow",
        "priority": "medium"
    },
    {
        "category": "Desarrollo",
        "objective": "Proyectos en GitLab",
        "dork": "site:gitlab.com \"{target_alias}\"",
        "description": "Encuentra proyectos o actividad en GitLab",
        "priority": "medium"
    },
    {
        "category": "Desarrollo",
        "objective": "Perfil en Hacker News",
        "dork": "site:news.ycombinator.com \"{target_alias}\"",
        "description": "Busca comentarios o menciones en Hacker News",
        "priority": "low"
    },
    
    # =========================================================================
    # Categoría: Plataformas de Contenido y Medios
    # =========================================================================
    {
        "category": "Contenido",
        "objective": "Artículos en Medium",
        "dork": "site:medium.com \"{target_alias}\" OR \"@{target_alias}\"",
        "description": "Encuentra artículos publicados o menciones en Medium",
        "priority": "medium"
    },
    {
        "category": "Contenido",
        "objective": "Blogs en WordPress",
        "dork": "site:wordpress.com \"{target_alias}\"",
        "description": "Busca blogs o menciones en WordPress.com",
        "priority": "low"
    },
    {
        "category": "Contenido",
        "objective": "Newsletters en Substack",
        "dork": "site:substack.com \"{target_alias}\"",
        "description": "Encuentra newsletters o menciones en Substack",
        "priority": "medium"
    },
    {
        "category": "Contenido",
        "objective": "Videos en YouTube",
        "dork": "site:youtube.com \"{target_alias}\"",
        "description": "Busca canales o menciones en YouTube",
        "priority": "medium"
    },
    
    # =========================================================================
    # Categoría: Documentos y Exposición de Información
    # =========================================================================
    {
        "category": "Documentos",
        "objective": "Documentos PDF públicos",
        "dork": "filetype:pdf \"{target_alias}\"",
        "description": "Encuentra documentos PDF que mencionen el alias",
        "priority": "medium"
    },
    {
        "category": "Documentos",
        "objective": "Presentaciones públicas",
        "dork": "(filetype:pptx OR filetype:ppt) \"{target_alias}\"",
        "description": "Busca presentaciones que mencionen el usuario",
        "priority": "low"
    },
    {
        "category": "Documentos",
        "objective": "Documentos de Word",
        "dork": "(filetype:docx OR filetype:doc) \"{target_alias}\"",
        "description": "Encuentra documentos de Word con menciones",
        "priority": "low"
    },
    
    # =========================================================================
    # Categoría: Foros y Comunidades
    # =========================================================================
    {
        "category": "Comunidades",
        "objective": "Actividad en foros generales",
        "dork": "\"{target_alias}\" (site:forum.* OR site:community.* OR inurl:forum)",
        "description": "Rastrea actividad en foros y comunidades online",
        "priority": "medium"
    },
    {
        "category": "Comunidades",
        "objective": "Menciones fuera de redes principales",
        "dork": "\"{target_alias}\" -site:x.com -site:facebook.com -site:instagram.com -site:linkedin.com",
        "description": "Encuentra menciones excluyendo las redes sociales principales",
        "priority": "high"
    },
    {
        "category": "Comunidades",
        "objective": "Actividad en Discord (enlaces)",
        "dork": "\"discord.gg\" \"{target_alias}\" OR \"discord.com\" \"{target_alias}\"",
        "description": "Busca enlaces o menciones relacionadas con Discord",
        "priority": "low"
    },
    
    # =========================================================================
    # Categoría: Actividad General y Variaciones
    # =========================================================================
    {
        "category": "General",
        "objective": "Búsqueda amplia con variaciones",
        "dork": "\"{target_alias}\" OR \"@{target_alias}\" OR \"{target_clean}\"",
        "description": "Búsqueda general con múltiples variaciones del alias",
        "priority": "high"
    },
    {
        "category": "General",
        "objective": "Menciones en títulos",
        "dork": "intitle:\"{target_alias}\" OR intitle:\"@{target_alias}\"",
        "description": "Busca páginas que tengan el alias en el título",
        "priority": "medium"
    },
    {
        "category": "General",
        "objective": "Menciones en URLs",
        "dork": "inurl:\"{target_alias}\"",
        "description": "Encuentra URLs que contengan el alias",
        "priority": "medium"
    }
]

# Dorking templates for domain analysis
domain_dorking_templates = [
    {
        "category": "Infraestructura",
        "objective": "Descubrimiento de subdominios",
        "dork": "site:*.{target_domain} -site:www.{target_domain}",
        "description": "Encuentra subdominios indexados por Google",
        "priority": "high"
    },
    {
        "category": "Infraestructura", 
        "objective": "Identificación de portales de login",
        "dork": "site:{target_domain} (intitle:\"Login\" | inurl:\"login\" | inurl:\"signin\" | inurl:\"auth\")",
        "description": "Localiza páginas de autenticación",
        "priority": "high"
    },
    {
        "category": "Información Sensible",
        "objective": "Documentos confidenciales",
        "dork": "site:{target_domain} (filetype:pdf | filetype:xlsx | filetype:docx) (\"confidencial\" | \"interno\" | \"privado\")",
        "description": "Encuentra documentos con información sensible",
        "priority": "high"
    }
]

# Priority levels for query execution
PRIORITY_LEVELS = {
    "high": 1,
    "medium": 2, 
    "low": 3
}

def get_templates_by_category(templates, category):
    """Filter templates by category"""
    return [t for t in templates if t["category"] == category]

def get_templates_by_priority(templates, priority):
    """Filter templates by priority level"""
    return [t for t in templates if t["priority"] == priority]

def format_dork_template(template, target_alias=None, target_domain=None, target_company=None):
    """
    Format a dork template with actual target values
    
    Args:
        template: The dork template dictionary
        target_alias: The alias/username to search for
        target_domain: The domain to search
        target_company: The company name to search
        
    Returns:
        Formatted dork string ready for search
    """
    dork = template["dork"]
    target_clean = target_alias.lstrip('@') if target_alias else ''
    
    # Replace placeholders
    formatted_dork = dork.format(
        target_alias=target_alias or '',
        target_clean=target_clean,
        target_domain=target_domain or '',
        target_company=target_company or ''
    )
    
    return formatted_dork