"""
Advanced OSINT Dorking Service
Provides sophisticated Google dorking capabilities using predefined templates
"""

import logging
from typing import Dict, List, Any, Optional, Tuple
from api.data.dorking_templates import (
    alias_dorking_templates, 
    domain_dorking_templates,
    PRIORITY_LEVELS,
    format_dork_template,
    get_templates_by_priority,
    get_templates_by_category
)
from api.clients import GoogleSearchClient, GoogleSearchAPIError

logger = logging.getLogger(__name__)


class DorkingService:
    """
    Service for advanced OSINT dorking analysis
    """
    
    def __init__(self, google_client: GoogleSearchClient):
        self.google_client = google_client
        
    async def analyze_alias_comprehensive(
        self, 
        alias: str,
        priority_filter: Optional[str] = None,
        category_filter: Optional[str] = None,
        max_results_per_query: int = 5
    ) -> Dict[str, Any]:
        """
        Perform comprehensive alias analysis using dorking templates
        
        Args:
            alias: The alias/username to analyze
            priority_filter: Filter by priority (high, medium, low)
            category_filter: Filter by category
            max_results_per_query: Maximum results per individual query
            
        Returns:
            Comprehensive analysis results
        """
        logger.info(f"Starting comprehensive alias analysis for: {alias}")
        
        # Clean the alias
        clean_alias = alias.lstrip('@')
        
        # Filter templates based on parameters
        templates = alias_dorking_templates
        
        if priority_filter:
            templates = get_templates_by_priority(templates, priority_filter)
            
        if category_filter:
            templates = get_templates_by_category(templates, category_filter)
            
        # Sort by priority (high priority first)
        templates.sort(key=lambda x: PRIORITY_LEVELS.get(x["priority"], 999))
        
        results = {
            "target_alias": alias,
            "clean_alias": clean_alias,
            "total_templates_used": len(templates),
            "analysis_results": {},
            "summary": {
                "total_results_found": 0,
                "successful_queries": 0,
                "failed_queries": 0,
                "categories_analyzed": set(),
                "high_value_findings": []
            }
        }
        
        # Execute each dorking template
        for i, template in enumerate(templates):
            try:
                # Format the dork query
                formatted_dork = format_dork_template(
                    template, 
                    target_alias=alias,
                    target_domain=None,
                    target_company=None
                )
                
                logger.info(f"Executing dork {i+1}/{len(templates)}: {template['objective']}")
                logger.debug(f"Query: {formatted_dork}")
                
                # Execute the search
                search_result = await self.google_client.search(
                    query=formatted_dork,
                    num_results=max_results_per_query
                )
                
                # Process results
                items = search_result.get('items', [])
                total_results = int(search_result.get('searchInformation', {}).get('totalResults', '0'))
                
                query_result = {
                    "template": template,
                    "formatted_query": formatted_dork,
                    "total_results": total_results,
                    "returned_items": len(items),
                    "items": items,
                    "status": "success",
                    "search_time": search_result.get('searchInformation', {}).get('searchTime', 0)
                }
                
                # Store result
                key = f"{template['category']}_{template['objective']}".replace(' ', '_').lower()
                results["analysis_results"][key] = query_result
                
                # Update summary
                results["summary"]["total_results_found"] += total_results
                results["summary"]["successful_queries"] += 1
                results["summary"]["categories_analyzed"].add(template["category"])
                
                # Mark high-value findings
                if total_results > 0 and template["priority"] == "high":
                    results["summary"]["high_value_findings"].append({
                        "objective": template["objective"],
                        "category": template["category"],
                        "results_count": total_results,
                        "preview_items": items[:3]  # First 3 items for preview
                    })
                    
                logger.info(f"Query completed: {total_results} total results, {len(items)} returned")
                
            except GoogleSearchAPIError as e:
                logger.error(f"Google Search API error for template '{template['objective']}': {e.message}")
                
                error_result = {
                    "template": template,
                    "formatted_query": formatted_dork,
                    "status": "api_error",
                    "error": e.message,
                    "error_code": e.status_code
                }
                
                key = f"{template['category']}_{template['objective']}".replace(' ', '_').lower()
                results["analysis_results"][key] = error_result
                results["summary"]["failed_queries"] += 1
                
            except Exception as e:
                logger.error(f"Unexpected error for template '{template['objective']}': {e}")
                
                error_result = {
                    "template": template,
                    "formatted_query": formatted_dork,
                    "status": "error",
                    "error": str(e)
                }
                
                key = f"{template['category']}_{template['objective']}".replace(' ', '_').lower()
                results["analysis_results"][key] = error_result
                results["summary"]["failed_queries"] += 1
        
        # Convert set to list for JSON serialization
        results["summary"]["categories_analyzed"] = list(results["summary"]["categories_analyzed"])
        
        logger.info(f"Comprehensive analysis completed. {results['summary']['successful_queries']} successful queries, {results['summary']['total_results_found']} total results found")
        
        return results
    
    async def analyze_domain_comprehensive(
        self,
        domain: str,
        priority_filter: Optional[str] = None,
        max_results_per_query: int = 5
    ) -> Dict[str, Any]:
        """
        Perform comprehensive domain analysis using dorking templates
        
        Args:
            domain: The domain to analyze
            priority_filter: Filter by priority (high, medium, low)
            max_results_per_query: Maximum results per individual query
            
        Returns:
            Comprehensive analysis results
        """
        logger.info(f"Starting comprehensive domain analysis for: {domain}")
        
        # Filter templates based on parameters
        templates = domain_dorking_templates
        
        if priority_filter:
            templates = get_templates_by_priority(templates, priority_filter)
            
        # Sort by priority
        templates.sort(key=lambda x: PRIORITY_LEVELS.get(x["priority"], 999))
        
        results = {
            "target_domain": domain,
            "total_templates_used": len(templates),
            "analysis_results": {},
            "summary": {
                "total_results_found": 0,
                "successful_queries": 0,
                "failed_queries": 0,
                "high_value_findings": []
            }
        }
        
        # Execute each template
        for template in templates:
            try:
                formatted_dork = format_dork_template(
                    template,
                    target_domain=domain
                )
                
                search_result = await self.google_client.search(
                    query=formatted_dork,
                    num_results=max_results_per_query
                )
                
                items = search_result.get('items', [])
                total_results = int(search_result.get('searchInformation', {}).get('totalResults', '0'))
                
                query_result = {
                    "template": template,
                    "formatted_query": formatted_dork,
                    "total_results": total_results,
                    "returned_items": len(items),
                    "items": items,
                    "status": "success"
                }
                
                key = f"{template['category']}_{template['objective']}".replace(' ', '_').lower()
                results["analysis_results"][key] = query_result
                
                results["summary"]["total_results_found"] += total_results
                results["summary"]["successful_queries"] += 1
                
                if total_results > 0 and template["priority"] == "high":
                    results["summary"]["high_value_findings"].append({
                        "objective": template["objective"],
                        "category": template["category"],
                        "results_count": total_results
                    })
                    
            except Exception as e:
                logger.error(f"Error in domain template '{template['objective']}': {e}")
                results["summary"]["failed_queries"] += 1
        
        return results
    
    def get_available_categories(self, target_type: str = "alias") -> List[str]:
        """Get available categories for a target type"""
        if target_type == "alias":
            return list(set(t["category"] for t in alias_dorking_templates))
        elif target_type == "domain":
            return list(set(t["category"] for t in domain_dorking_templates))
        return []
    
    def get_templates_info(self, target_type: str = "alias") -> List[Dict[str, Any]]:
        """Get information about available templates"""
        templates = alias_dorking_templates if target_type == "alias" else domain_dorking_templates
        
        return [{
            "category": t["category"],
            "objective": t["objective"],
            "description": t["description"],
            "priority": t["priority"],
            "template": t["dork"]
        } for t in templates]